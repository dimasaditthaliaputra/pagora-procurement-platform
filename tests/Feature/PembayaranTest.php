<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditNoteStatus;
use App\Enums\PaymentStatus;
use App\Enums\PemicuSengketa;
use App\Enums\RoleName;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PerusahaanBuyer;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Invoice payment through Midtrans (UC-17 steps 5–7, FR-29, FR-39, BR-13, BR-26, NFR-04).
 * Midtrans is faked with `Http::fake`; no call leaves the test.
 */
class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendorA;

    private User $ownerA;

    private User $purchasingA;

    private User $qcA;

    private User $manajemenB;

    private User $vendorUserA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        config([
            'services.midtrans.server_key' => self::SERVER_KEY,
            'services.midtrans.client_key' => 'SB-Mid-client-test',
            'services.midtrans.is_production' => false,
        ]);

        $this->tenantA = PerusahaanBuyer::factory()->create();
        $this->tenantB = PerusahaanBuyer::factory()->create();
        $this->vendorA = $this->makeVendor('CV Sumber Tani');

        $this->ownerA = $this->makeUser(RoleName::Owner, $this->tenantA->id, null);
        $this->purchasingA = $this->makeUser(RoleName::Purchasing, $this->tenantA->id, null);
        $this->qcA = $this->makeUser(RoleName::QcGudang, $this->tenantA->id, null);
        $this->manajemenB = $this->makeUser(RoleName::Manajemen, $this->tenantB->id, null);
        $this->vendorUserA = $this->makeUser(RoleName::Vendor, null, $this->vendorA->id);

        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-1',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            ], 201),
        ]);
    }

    public function test_purchasing_starts_a_payment_for_the_full_bill(): void
    {
        $invoice = $this->matchedInvoice('21000000');

        $response = $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-1')
            ->assertJsonPath('data.client_key', 'SB-Mid-client-test')
            ->assertJsonPath('data.snap_url', 'https://app.sandbox.midtrans.com/snap/snap.js');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->payment_status);
        $this->assertSame('21000000.00', $payment->amount);
        $this->assertMatchesRegularExpression('/^PAY-\d{4}-0001$/', $payment->payment_number);
        $this->assertSame($this->purchasingA->id, $payment->created_by);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SERVER_KEY.':'))
                && $request['transaction_details']['gross_amount'] === 21000000
                && str_starts_with($request['transaction_details']['order_id'], 'PAY-');
        });
    }

    public function test_amount_is_the_bill_minus_confirmed_credit_notes_only(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $dispute = $this->resolvedDispute($invoice);
        $this->creditNote($invoice, $dispute, '1000000', CreditNoteStatus::Confirmed);
        $this->creditNote($invoice, $dispute, '500000', CreditNoteStatus::Rejected);

        $this->actingAs($this->ownerA)->postJson("/pembayaran/{$invoice->uuid}")->assertOk();

        $this->assertSame('20000000.00', Payment::query()->firstOrFail()->amount);
    }

    public function test_a_pending_payment_is_resumed_instead_of_duplicated(): void
    {
        $invoice = $this->matchedInvoice('21000000');

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}")->assertOk();
        $this->actingAs($this->ownerA)->postJson("/pembayaran/{$invoice->uuid}")->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-1');

        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(1);
    }

    public function test_payment_is_refused_unless_the_invoice_is_ready_to_pay(): void
    {
        $unmatched = $this->matchedInvoice('1000000', matching: 'pending');
        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$unmatched->uuid}")->assertForbidden();

        $notReady = $this->matchedInvoice('1000000', poStatus: StatusPurchaseOrder::Ditagih);
        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$notReady->uuid}")->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_payment_is_refused_while_a_dispute_is_unresolved(): void
    {
        $invoice = $this->matchedInvoice('1000000');
        Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $invoice->purchase_order_id,
            'invoice_id' => $invoice->id,
            'trigger_type' => PemicuSengketa::AmountMismatch->value,
            'reason' => 'Sengketa baru atas tagihan.',
            'opened_by' => $this->purchasingA->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now(),
        ]);

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_only_owner_and_purchasing_of_the_tenant_may_pay(): void
    {
        $invoice = $this->matchedInvoice('1000000');

        $this->actingAs($this->qcA)->postJson("/pembayaran/{$invoice->uuid}")->assertForbidden();
        $this->actingAs($this->vendorUserA)->postJson("/pembayaran/{$invoice->uuid}")->assertForbidden();
        $this->actingAs($this->manajemenB)->postJson("/pembayaran/{$invoice->uuid}")->assertForbidden();
        $this->actingAs($this->makeUser(RoleName::Purchasing, $this->tenantB->id, null))
            ->postJson("/pembayaran/{$invoice->uuid}")->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_gateway_error_leaves_no_payment_behind(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['error_messages' => ['Access denied']], 401)]);
        $invoice = $this->matchedInvoice('1000000');

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}")->assertStatus(500);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_settlement_notification_pays_the_invoice_and_the_purchase_order(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');

        // Public endpoint: no login and no CSRF token.
        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'settlement'))->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->payment_status);
        $this->assertSame('bank_transfer', $payment->payment_method);
        $this->assertSame('trx-123', $payment->reference_number);
        $this->assertNotNull($payment->payment_date);
        $this->assertSame(StatusPurchaseOrder::Lunas, $invoice->purchaseOrder()->first()->status);
    }

    public function test_notification_is_idempotent_and_a_paid_payment_never_reverts(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');

        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'settlement'))->assertOk();
        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'settlement'))->assertOk();
        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'expire'))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->payment_status);
    }

    public function test_notification_with_a_bad_signature_is_rejected(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');

        $forged = [...$this->notification($payment, 'settlement'), 'signature_key' => str_repeat('a', 128)];
        $this->postJson('/pembayaran/midtrans/notifikasi', $forged)->assertForbidden();

        $tampered = $this->notification($payment, 'settlement');
        $tampered['gross_amount'] = '1.00';
        $this->postJson('/pembayaran/midtrans/notifikasi', $tampered)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->payment_status);
        $this->assertSame(StatusPurchaseOrder::ReadyToPay, $invoice->purchaseOrder()->first()->status);
    }

    public function test_notification_for_an_unknown_order_or_other_amount_is_rejected(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');

        $unknown = $this->signed(['order_id' => 'PAY-0000-0000-XXXXXX', 'status_code' => '200', 'gross_amount' => '21000000.00', 'transaction_status' => 'settlement']);
        $this->postJson('/pembayaran/midtrans/notifikasi', $unknown)->assertNotFound();

        $otherAmount = $this->signed(['order_id' => $payment->gateway_order_id, 'status_code' => '200', 'gross_amount' => '5000.00', 'transaction_status' => 'settlement']);
        $this->postJson('/pembayaran/midtrans/notifikasi', $otherAmount)->assertStatus(422);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->payment_status);
    }

    public function test_failed_payment_can_be_retried_with_a_new_payment(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');

        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'expire'))->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->payment_status);
        $this->assertSame(StatusPurchaseOrder::ReadyToPay, $invoice->purchaseOrder()->first()->status);

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}")->assertOk();

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(1, Payment::query()->where('payment_status', 'pending')->count());
    }

    public function test_purchase_order_stays_ready_to_pay_while_another_invoice_is_unpaid(): void
    {
        $first = $this->matchedInvoice('1000000');
        $second = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $first->purchase_order_id,
            'vendor_id' => $this->vendorA->id,
            'nomor_invoice_vendor' => 'INV-'.Str::upper(Str::random(6)),
            'jumlah_tagihan' => 2000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => 'matched',
        ]);
        $payment = $this->pendingPayment($first, '1000000');

        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'settlement'))->assertOk();
        $this->assertSame(StatusPurchaseOrder::ReadyToPay, $first->purchaseOrder()->first()->status);

        $paySecond = $this->pendingPayment($second, '2000000');
        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($paySecond, 'settlement'))->assertOk();
        $this->assertSame(StatusPurchaseOrder::Lunas, $first->purchaseOrder()->first()->status);
    }

    public function test_a_fully_paid_invoice_cannot_be_paid_again(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');
        $this->postJson('/pembayaran/midtrans/notifikasi', $this->notification($payment, 'settlement'))->assertOk();

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}")->assertForbidden();
    }

    public function test_sync_pulls_the_status_from_midtrans(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');
        Http::fake([
            "api.sandbox.midtrans.com/v2/{$payment->gateway_order_id}/status" => Http::response([
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
                'transaction_id' => 'trx-sync',
                'settlement_time' => '2026-11-20 10:15:00',
            ]),
        ]);

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}/sinkron")->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->payment_status);
        $this->assertSame('qris', $payment->payment_method);
        $this->assertSame(StatusPurchaseOrder::Lunas, $invoice->purchaseOrder()->first()->status);
    }

    public function test_sync_keeps_the_payment_pending_when_midtrans_has_no_record(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $payment = $this->pendingPayment($invoice, '21000000');
        Http::fake(["api.sandbox.midtrans.com/v2/{$payment->gateway_order_id}/status" => Http::response(['status_message' => 'Transaction doesn\'t exist.'], 404)]);

        $this->actingAs($this->purchasingA)->postJson("/pembayaran/{$invoice->uuid}/sinkron")->assertOk()
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_detail_page_exposes_the_payment_state(): void
    {
        $invoice = $this->matchedInvoice('21000000');
        $dispute = $this->resolvedDispute($invoice);
        $this->creditNote($invoice, $dispute, '1000000', CreditNoteStatus::Confirmed);
        $this->pendingPayment($invoice, '20000000');

        $this->actingAs($this->purchasingA)->get("/verifikasi-tagihan/{$invoice->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VerifikasiTagihan/Show')
                ->where('can_pay', true)
                ->where('remaining_amount', 20000000)
                ->has('invoice.data.payments', 1)
                ->where('invoice.data.payments.0.status', 'pending')
                ->missing('invoice.data.payments.0.snap_token'));

        $this->actingAs($this->qcA)->getJson("/verifikasi-tagihan/{$invoice->uuid}")->assertForbidden();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function notification(Payment $payment, string $status, array $overrides = []): array
    {
        return $this->signed([
            'order_id' => $payment->gateway_order_id,
            'status_code' => '200',
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_status' => $status,
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-123',
            'transaction_time' => '2026-11-20 10:00:00',
            'settlement_time' => '2026-11-20 10:05:00',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function signed(array $payload): array
    {
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::SERVER_KEY);

        return $payload;
    }

    private function pendingPayment(Invoice $invoice, string $amount): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-2026-'.str_pad((string) (Payment::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'gateway_order_id' => 'ORDER-'.Str::upper(Str::random(8)),
            'amount' => $amount,
            'payment_status' => PaymentStatus::Pending->value,
            'snap_token' => 'snap-'.Str::random(8),
            'created_by' => $this->purchasingA->id,
        ]);
    }

    private function matchedInvoice(string $amount, string $matching = 'matched', StatusPurchaseOrder $poStatus = StatusPurchaseOrder::ReadyToPay): Invoice
    {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-TEST-'.Str::upper(Str::random(6)),
            'perusahaan_buyer_id' => $this->tenantA->id,
            'vendor_id' => $this->vendorA->id,
            'status' => $poStatus->value,
            'tanggal_po' => now()->toDateString(),
            'created_by' => $this->purchasingA->id,
            'locked_at' => now(),
        ]);

        return Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendorA->id,
            'nomor_invoice_vendor' => 'INV-'.Str::upper(Str::random(6)),
            'jumlah_tagihan' => $amount,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => $matching,
        ]);
    }

    private function resolvedDispute(Invoice $invoice): Sengketa
    {
        return Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $invoice->purchase_order_id,
            'invoice_id' => $invoice->id,
            'trigger_type' => PemicuSengketa::AmountMismatch->value,
            'reason' => 'Selisih harga satuan.',
            'opened_by' => $this->purchasingA->id,
            'status' => StatusSengketa::Resolved->value,
            'opened_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    private function creditNote(Invoice $invoice, Sengketa $dispute, string $amount, CreditNoteStatus $status): CreditNote
    {
        return CreditNote::factory()->create([
            'invoice_id' => $invoice->id,
            'dispute_id' => $dispute->id,
            'amount' => $amount,
            'status' => $status,
            'created_by' => $this->ownerA->id,
        ]);
    }

    private function makeVendor(string $name): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'contact_email' => Str::lower(Str::random(6)).'@mitra.test',
            'phone' => '0812'.random_int(10000000, 99999999),
            'address' => 'Jl. Raya No. 1',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function makeUser(RoleName $role, ?int $tenantId, ?int $vendorId): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'is_active' => true,
        ]);
    }
}
