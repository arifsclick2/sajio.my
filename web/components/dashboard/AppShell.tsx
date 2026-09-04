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
  { key: "home", label: "Home", icon: "▦", href: "/dashboard" },
  { key: "pos", label: "POS", icon: "◧", href: "/dashboard/pos" },
  { key: "menu", label: "Menu", icon: "☰", href: "/dashboard/menu", roles: ["owner", "manager"] },
  { key: "tables", label: "Tables", icon: "▤", href: "/dashboard/tables", roles: ["owner", "manager"] },
  { key: "staff", label: "Staff", icon: "☺", href: "/dashboard/staff", roles: ["owner", "manager"] },
  { key: "sales", label: "Sales", icon: "₨", href: "/dashboard/sales" },
];

function Logo({ light = false }: { light?: boolean }) {
  return (
    <Link href="/dashboard" className={`inline-flex items-center gap-2 text-lg font-extrabold tracking-tight ${light ? "text-white" : "text-ink"}`}>
      <span className="grid h-8 w-8 place-items-center rounded-xl bg-rasa-500 text-sm font-black text-white shadow-md shadow-rasa-500/30">S</span>
      sajio<span className="text-rasa-500">.</span>
    </Link>
  );
}

export default function AppShell({ active, children }: { active: string; children: ReactNode }) {
  const router = useRouter();
  const [status, setStatus] = useState<"loading" | "ready">("loading");
  const [user, setUser] = useState<User | null>(getStoredUser());
  const [restaurant] = useState(getStoredRestaurant());
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
      setClockMsg(err instanceof Error ? err.message : "Failed.");
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
      <div className="grid min-h-dvh place-items-center bg-[#fdf8f6]">
        <div className="text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-rasa-500 text-lg font-black text-white">S</div>
          <p className="mt-3 text-sm font-bold text-stone-400">Loading…</p>
        </div>
      </div>
    );
  }

  return (
    <Ctx.Provider value={{ user, role, restaurant, billing, attendance, clockIn: () => handleClock("in"), clockOut: () => handleClock("out"), logout: handleLogout }}>
      <div className="min-h-dvh bg-[#fdf8f6]">
        {/* ================= TOP HEADER (POS-style) ================= */}
        <header className="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
          <div className="mx-auto flex h-16 max-w-[1600px] items-center gap-3 px-4 sm:px-6">
            <Logo />

            {/* Restaurant identity */}
            <div className="hidden min-w-0 md:block">
              <p className="truncate text-sm font-black leading-tight text-ink">{restaurant?.name ?? user?.email ?? ""}</p>
              {restaurant && <p className="truncate text-[11px] font-medium leading-tight text-stone-400">{restaurant.subdomain}.sajio.my</p>}
            </div>

            {/* Inline role pill */}
            <span className="hidden rounded-full bg-rasa-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-rasa-600 ring-1 ring-rasa-100 sm:inline-block">
              {role === "owner" ? "Owner" : role === "manager" ? "Manager" : role === "staff" ? "Staff" : role ?? ""}
            </span>

            {/* Primary navigation in the header */}
            <nav className="ml-auto flex items-center gap-1 overflow-x-auto rounded-2xl bg-stone-100/80 p-1">
              {nav.map((n) => {
                const on = active === n.key;
                return (
                  <Link
                    key={n.key}
                    href={n.href}
                    className={`flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-black transition ${
                      on ? "bg-rasa-500 text-white shadow-md shadow-rasa-500/30" : "text-stone-600 hover:bg-white hover:text-rasa-600"
                    }`}
                  >
                    {n.label}
                  </Link>
                );
              })}
            </nav>

            {/* Status chips + actions */}
            <div className="flex items-center gap-2">
              {billing && role === "owner" && (
                billing.package ? (
                  <span className="hidden rounded-full bg-ink px-3 py-1 text-xs font-black text-white lg:inline-block">{billing.package.name}</span>
                ) : billing.needs_subscription ? (
                  <span className="hidden rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-600 lg:inline-block">Locked</span>
                ) : (
                  <span className="hidden rounded-full bg-rasa-50 px-3 py-1 text-xs font-black text-rasa-600 ring-1 ring-rasa-200 lg:inline-block">
                    Trial · {billing.trial.days_remaining}d left
                  </span>
                )
              )}

              {attendance?.requires_attendance && (
                <button
                  onClick={() => handleClock(attendance.on_duty ? "out" : "in")}
                  className={`hidden rounded-xl px-3 py-1.5 text-xs font-black transition sm:block ${
                    attendance.on_duty ? "bg-emerald-600 text-white hover:bg-emerald-500" : "bg-stone-200 text-stone-600 hover:bg-stone-300"
                  }`}
                >
                  {attendance.on_duty ? "Clock out" : "Clock in"}
                </button>
              )}

              <button onClick={handleLogout} className="rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-bold text-stone-600 transition hover:border-rasa-300 hover:text-rasa-600">
                Log out
              </button>
            </div>
          </div>
        </header>

        {/* Content */}
        <main className="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">{children}</main>

        {/* Clock message toast */}
        {clockMsg && (
          <div className="fixed bottom-5 right-5 z-50 rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-sm font-black text-emerald-700 shadow-2xl">
            {clockMsg}
          </div>
        )}
      </div>
    </Ctx.Provider>
  );
}
