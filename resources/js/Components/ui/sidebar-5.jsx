import React, { useState } from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from "@/Components/ui/sidebar";
import {
  Bell,
  Search,
  Gauge,
  CheckSquare,
  Package,
  User,
  ShieldCheck,
  Settings,
  Crown,
  PlusSquare,
  Sparkles,
} from "lucide-react";

//  ------------------------------ | COMPONENT - SIDEBAR 5 (Pro) | ------------------------------  //

const mainNav = [
  { icon: Gauge, label: "Dashboard", id: "dashboard", badge: null },
  { icon: CheckSquare, label: "Projects", id: "projects", badge: "12" },
  { icon: Package, label: "Integrations", id: "integrations", badge: null },
];

const settingsNav = [
  { icon: User, label: "Profile", id: "profile" },
  { icon: ShieldCheck, label: "Security", id: "security" },
  { icon: Settings, label: "Preferences", id: "preferences" },
];

function SidebarLogo() {
  return (
    <div className="flex items-center gap-2.5 font-bold tracking-tight text-foreground">
      <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <Sparkles className="size-4" />
      </div>
      <span className="text-base font-semibold">Pagora Pro</span>
    </div>
  );
}

export default function Sidebar5() {
  const [activeItem, setActiveItem] = useState("dashboard");

  return (
    <SidebarProvider
      style={{ "--sidebar-width": "18rem" }}
      className="relative h-[600px] w-full overflow-hidden rounded-xl border bg-background shadow-sm"
    >
      <Sidebar className="absolute z-10 h-full border-r border-border bg-sidebar/50 backdrop-blur-md">
        <SidebarHeader className="border-b border-border/50 px-6 py-5">
          <div className="flex items-center justify-between">
            <SidebarLogo />
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <Bell className="size-4" />
            </Button>
          </div>
          <div className="relative mt-4 w-full">
            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search..."
              className="h-9 bg-background/50 pl-9 shadow-none hover:bg-background focus:bg-background"
            />
          </div>
        </SidebarHeader>

        <SidebarContent className="px-4 py-4">
          <SidebarGroup>
            <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-muted-foreground/70 uppercase">
              Main Menu
            </SidebarGroupLabel>
            <SidebarGroupContent className="mt-2">
              <SidebarMenu className="gap-1.5">
                {mainNav.map((item) => (
                  <SidebarMenuItem key={item.id}>
                    <SidebarMenuButton
                      isActive={activeItem === item.id}
                      onClick={() => setActiveItem(item.id)}
                      className="group flex h-10 w-full items-center justify-between rounded-lg px-3 transition-all hover:bg-primary/5 data-[active=true]:bg-primary data-[active=true]:text-primary-foreground data-[active=true]:hover:bg-primary/90"
                    >
                      <div className="flex items-center gap-3">
                        <item.icon className="size-5 transition-transform group-hover:scale-110" />
                        <span className="font-medium">{item.label}</span>
                      </div>
                      {item.badge && (
                        <Badge className="ml-auto h-5 px-1.5 text-[10px]">
                          {item.badge}
                        </Badge>
                      )}
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>

          <SidebarGroup className="mt-6">
            <SidebarGroupLabel className="px-2 text-[11px] font-semibold tracking-wider text-muted-foreground/70 uppercase">
              Settings
            </SidebarGroupLabel>
            <SidebarGroupContent className="mt-2">
              <SidebarMenu className="gap-1.5">
                {settingsNav.map((item) => (
                  <SidebarMenuItem key={item.id}>
                    <SidebarMenuButton
                      isActive={activeItem === item.id}
                      onClick={() => setActiveItem(item.id)}
                      className="group flex h-10 w-full items-center gap-3 rounded-lg px-3 transition-all hover:bg-primary/5 data-[active=true]:bg-primary data-[active=true]:text-primary-foreground data-[active=true]:hover:bg-primary/90"
                    >
                      <item.icon className="size-5 transition-transform group-hover:scale-110" />
                      <span className="font-medium">{item.label}</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        </SidebarContent>

        <div className="px-4">
          {/* Pro Upgrade Card */}
          <div className="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/15 via-primary/5 to-primary/10 p-4 dark:from-primary/20 dark:via-primary/10 dark:to-primary/20">
            <div className="mb-2 flex items-center gap-2">
              <Crown className="size-5 text-primary" />
              <h5 className="text-sm font-semibold text-foreground">
                Pro Workspace
              </h5>
            </div>
            <p className="mb-4 text-xs text-muted-foreground">
              Unlock premium features and advanced analytics.
            </p>
            <Button variant="default" className="w-full gap-2">
              <PlusSquare className="size-4" />
              Upgrade Now
            </Button>
          </div>
        </div>

        <SidebarFooter className="mt-4 border-t border-border/50 p-4">
          <div className="flex w-full cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted/50">
            <Avatar className="h-9 w-9">
              <AvatarImage
                src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80"
                alt="User"
              />
              <AvatarFallback className="bg-primary/10 font-bold text-primary">
                AD
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-1 flex-col overflow-hidden">
              <span className="truncate text-sm font-semibold">Alex Doe</span>
              <span className="truncate text-xs text-muted-foreground">
                alex@example.com
              </span>
            </div>
            <Settings className="size-4 text-muted-foreground" />
          </div>
        </SidebarFooter>
      </Sidebar>

      <main className="flex-1 overflow-auto bg-muted/20">
        <header className="flex h-14 items-center border-b border-border/50 bg-background/50 px-4 backdrop-blur">
          <SidebarTrigger className="-ml-1" />
        </header>
      </main>
    </SidebarProvider>
  );
}

export function Sidebar5Demo() {
  return (
    <div className="flex w-full items-center justify-center p-8">
      <Sidebar5 />
    </div>
  );
}
