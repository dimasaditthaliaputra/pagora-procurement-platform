import React from 'react';
import { Button } from '@/Components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/Components/ui/empty';

/**
 * Full-screen error state with a large faded status code.
 *
 * Props:
 * - code: status code shown as the headline (e.g. 404)
 * - title: short error title
 * - description: explanation for the user
 * - actions: array of { label, icon, href?, onClick?, variant? }
 */
export function ErrorState({ code, title, description, actions = [] }) {
    return (
        <div className="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-background px-4">
            <Empty>
                <EmptyHeader>
                    <EmptyTitle className="mask-b-from-20% mask-b-to-80% text-8xl font-extrabold sm:text-9xl">
                        {code}
                    </EmptyTitle>
                    <EmptyDescription className="-mt-6 space-y-1 text-foreground/80">
                        <span className="block text-base font-semibold text-foreground">{title}</span>
                        <span className="block">{description}</span>
                    </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <div className="flex flex-wrap justify-center gap-2">
                        {actions.map(({ label, icon: Icon, href, onClick, variant = 'default' }) => (
                            <Button key={label} variant={variant} asChild={!!href} onClick={onClick}>
                                {href ? (
                                    <a href={href}>
                                        <Icon data-icon="inline-start" />
                                        {label}
                                    </a>
                                ) : (
                                    <>
                                        <Icon data-icon="inline-start" />
                                        {label}
                                    </>
                                )}
                            </Button>
                        ))}
                    </div>
                </EmptyContent>
            </Empty>
        </div>
    );
}

export default ErrorState;
