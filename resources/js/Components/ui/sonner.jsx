"use client"

import { useTheme } from "next-themes"
import { Toaster as Sonner } from "sonner";
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon, XIcon } from "lucide-react"

const Toaster = ({
  position = "bottom-right",
  richColors = true,
  closeButton = true,
  ...props
}) => {
  const { theme = "system" } = useTheme()

  return (
    <Sonner
      theme={theme}
      position={position}
      richColors={richColors}
      closeButton={closeButton}
      className="toaster group"
      icons={{
        success: (
          <CircleCheckIcon className="size-4" />
        ),
        info: (
          <InfoIcon className="size-4" />
        ),
        warning: (
          <TriangleAlertIcon className="size-4" />
        ),
        error: (
          <OctagonXIcon className="size-4" />
        ),
        loading: (
          <Loader2Icon className="size-4 animate-spin" />
        ),
        close: (
          <XIcon className="size-3.5" />
        ),
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
          "--toast-close-button-start": "unset",
          "--toast-close-button-end": "0",
          "--toast-close-button-transform": "translate(35%, -35%)",
        }
      }
      toastOptions={{
        closeButton: true,
        closeButtonAriaLabel: "Tutup notifikasi",
        classNames: {
          toast: "cn-toast",
          closeButton: "!border !border-border !bg-background !text-muted-foreground hover:!text-foreground hover:!bg-muted !transition-colors !cursor-pointer shadow-xs",
        },
      }}
      {...props}
    />
  );
}

export { Toaster }
