import type { Metadata, Viewport } from "next";
import { Outfit } from "next/font/google";
import "./globals.css";

const outfit = Outfit({
  subsets: ["latin"],
  variable: "--font-outfit",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://sajio.my"),
  title: {
    default: "Sajio — Simple POS. Simple Ordering. Simple Business.",
    template: "%s · Sajio",
  },
  description:
    "The simple POS, ordering and table management platform for Malaysian restaurants, cafes and warungs. Jalankan restoran anda dengan lebih mudah — 14-day free trial, no credit card required.",
  openGraph: {
    type: "website",
    locale: "en_MY",
    siteName: "Sajio",
  },
};

export const viewport: Viewport = {
  themeColor: "#faf6ee",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" className={`${outfit.variable} antialiased`}>
      <body className="flex min-h-dvh flex-col">{children}</body>
    </html>
  );
}
