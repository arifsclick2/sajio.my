"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api, BillingStatus, User } from "../../lib/api";
import { clearSession, getStoredRestaurant, getStoredUser, getToken } from "../../lib/session";

export type Role = "owner" | "manager" | "staff" | "super_admin" | undefined;

interface AttendanceChip {
  on_duty: boolean;
  requires_attendance: boolean;
}

interface CtxType {
  user: User | null;
  role: Role;
  restaurant: ReturnType<typeof getStoredRestaurant>;
  billing: BillingStatus | null;
  attendance: AttendanceChip | null;
  clockIn: () => Promise<void>;
  clockOut: () => Promise<void>;
  logout: () => void;
}

const Ctx = createContext<CtxType | null>(null);

export function useDashboard(): CtxType {
  const c = useContext(Ctx);
  if (!c) throw new Error("useDashboard must be used inside <AppShell>");
  return c;
}

const NAV: { key: string; label: string; icon: string; href: string; roles?: Role[] }[] = [
  { key: "home", label: "Dashboard", icon: "📊", href: "/dashboard" },
  { key: "pos", label: "POS", icon: "🧾", href: "/dashboard/pos" },
  { key: "menu", label: "Menu", icon: "🍽️", href: "/dashboard/menu", roles: ["owner", "manager"] },
  { key: "meja", label: "Meja", icon: "🪑", href: "/dashboard/meja", roles: ["owner", "manager"] },
  { key: "sales", label: "Bil & Jualan", icon: "💰", href: "/dashboard/sales" },
];

export default function AppShell({
  active,
  children,
}: {
  active: string;
  children: ReactNode;
}) {
  const router = useRouter();
  const [status, setStatus] = useState<"loading" | "ready">("loading");
  const [user, setUser] = useState<User | null>(getStoredUser());
  const [restaurant, setRestaurant] = useState(getStoredRestaurant());
  const [billing, setBilling] = useState<BillingStatus | null>(null);
  const [attendance, setAttendance] = useState<AttendanceChip | null>(null);
  const [clockMsg, setClockMsg] = useState<string | null>(null);

  useEffect(() => {
    const token = getToken();
    if (!token) {
      router.replace("/login");
      return;
    }
    (async () => {
      try {
        const me = await api.me(token);
        setUser(me);
        if (me.role === "owner") {
          const b = await api.billingStatus(token);
          setBilling(b);
          setRestaurant((r) => r ?? getStoredRestaurant());
        } else {
          const a = await api.attendanceToday(token);
          setAttendance({ on_duty: a.on_duty, requires_attendance: a.requires_attendance });
        }
      } catch {
        clearSession();
        router.replace("/login");
        return;
      } finally {
        setStatus("ready");
      }
    })();
  }, [router]);

  const role = user?.role as Role;
  const nav = NAV.filter((n) => !n.roles || (role && n.roles.includes(role)));

  async function handleClock(action: "in" | "out") {
    const token = getToken();
    if (!token) return;
    setClockMsg(null);
    try {
      const res = action === "in" ? await api.clockIn(token) : await api.clockOut(token);
      setClockMsg(res.message);
      const a = await api.attendanceToday(token);
      setAttendance({ on_duty: a.on_duty, requires_attendance: a.requires_attendance });
    } catch (err) {
      setClockMsg(err instanceof Error ? err.message : "Gagal.");
    }
  }

  function handleLogout() {
    const t = getToken();
    if (t) api.logout(t).catch(() => {});
    clearSession();
    router.replace("/");
  }

  if (status === "loading") {
    return (
      <div className="grid min-h-dvh place-items-center bg-[#f4efe4]">
        <div className="text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-brand-600 to-emerald-500 text-lg font-black text-white">S</div>
          <p className="mt-3 text-sm font-bold text-stone-400">Memuatkan…</p>
        </div>
      </div>
    );
  }

  return (
    <Ctx.Provider value={{ user, role, restaurant, billing, attendance, clockIn: () => handleClock("in"), clockOut: () => handleClock("out"), logout: handleLogout }}>
      <div className="min-h-dvh bg-[#f4efe4]">
        {/* Top bar */}
        <header className="sticky top-0 z-40 border-b border-stone-200/70 bg-white/90 backdrop-blur">
          <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
            <div className="flex min-w-0 items-center gap-3">
              <Link href="/dashboard" className="text-lg font-extrabold tracking-tight text-ink">
                <span className="mr-1 inline-grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-brand-600 to-emerald-500 align-middle text-xs font-black text-white">S</span>
                sajio<span className="text-brand-600">.</span>
              </Link>
              <div className="hidden min-w-0 sm:block">
                <p className="truncate text-sm font-black leading-tight text-ink">{restaurant?.name ?? "Dashboard"}</p>
                <p className="truncate text-[11px] font-medium leading-tight text-stone-400">
                  {restaurant ? `${restaurant.subdomain}.sajio.my` : user?.email ?? ""}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              {billing && role === "owner" && (
                billing.package ? (
                  <span className="hidden rounded-full bg-stone-800 px-3 py-1 text-xs font-black text-gold-300 sm:inline-block">
                    {billing.package.name}
                  </span>
                ) : billing.needs_subscription ? (
                  <span className="hidden rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-600 sm:inline-block">⛔ Kunci</span>
                ) : (
                  <span className="hidden rounded-full bg-brand-100 px-3 py-1 text-xs font-black text-brand-700 sm:inline-block">
                    TRIAL · {billing.trial.ends_at ? new Date(billing.trial.ends_at).toLocaleDateString("en-MY", { day: "numeric", month: "short" }) : ""}
                  </span>
                )
              )}
              {attendance?.requires_attendance && (
                <span className={`hidden rounded-full px-3 py-1 text-xs font-black sm:inline-block ${attendance.on_duty ? "bg-emerald-100 text-emerald-700" : "bg-stone-200 text-stone-600"}`}>
                  {attendance.on_duty ? "● BERTUGAS" : "○ BELUM MASUK"}
                </span>
              )}
              {attendance?.requires_attendance && !attendance.on_duty && (
                <button
                  onClick={() => handleClock("in")}
                  className="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-black text-white transition hover:bg-emerald-500"
                >
                  Clock in
                </button>
              )}
              {clockMsg && <span className="hidden text-xs font-semibold text-emerald-600 lg:inline">{clockMsg}</span>}
              <button onClick={handleLogout} className="rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-bold text-stone-600 transition hover:border-red-300 hover:text-red-600">
                Log keluar
              </button>
            </div>
          </div>
        </header>

        <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[210px_1fr]">
          {/* Sidebar */}
          <aside className="h-fit space-y-1 rounded-2xl border border-stone-200 bg-white p-3 lg:sticky lg:top-20">
            {nav.map((n) => {
              const isActive = n.key === active;
              return (
                <Link
                  key={n.key}
                  href={n.href}
                  className={`flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-bold transition ${
                    isActive ? "bg-brand-700 text-white shadow-sm" : "text-stone-600 hover:bg-brand-50 hover:text-brand-800"
                  }`}
                >
                  <span>{n.icon}</span>
                  <span className="flex-1">{n.label}</span>
                </Link>
              );
            })}
          </aside>

          <section className="min-w-0 space-y-6">{children}</section>
        </div>
      </div>
    </Ctx.Provider>
  );
}
