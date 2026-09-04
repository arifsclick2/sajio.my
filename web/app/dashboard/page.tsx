"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, BillingStatus } from "../../lib/api";
import { clearSession, getStoredRestaurant, getStoredUser, getToken } from "../../lib/session";
import { useRouter } from "next/navigation";

type Role = "owner" | "manager" | "staff" | "super_admin" | undefined;

interface Pkg { id: number; name: string; slug: string; description?: string; price_monthly: string }

export default function DashboardPage() {
  const router = useRouter();
  const token = getToken();
  const user = getStoredUser();
  const restaurant = getStoredRestaurant();

  const [checking, setChecking] = useState(true);
  const [billing, setBilling] = useState<BillingStatus | null>(null);
  const [attendance, setAttendance] = useState<{ on_duty: boolean; requires_attendance: boolean } | null>(null);
  const [clockMsg, setClockMsg] = useState<string | null>(null);
  const [clockBusy, setClockBusy] = useState(false);
  const [plans, setPlans] = useState<Pkg[] | null>(null);
  const [subMsg, setSubMsg] = useState<string | null>(null);

  const role = user?.role as Role;

  useEffect(() => {
    if (!token) {
      router.replace("/login");
      return;
    }
    (async () => {
      try {
        const me = await api.me(token);
        // Owner → billing status; staff/manager → attendance state
        if (me.role === "owner") {
          const b = await api.billingStatus(token);
          setBilling(b);
        } else {
          const a = await api.attendanceToday(token);
          setAttendance({ on_duty: a.on_duty, requires_attendance: a.requires_attendance });
        }
      } catch {
        // token invalid
        clearSession();
        router.replace("/login");
        return;
      } finally {
        setChecking(false);
      }
    })();
  }, [token, router]);

  async function handleClock(action: "in" | "out") {
    if (!token) return;
    setClockBusy(true);
    setClockMsg(null);
    try {
      const res = action === "in" ? await api.clockIn(token) : await api.clockOut(token);
      setClockMsg(res.message);
      const a = await api.attendanceToday(token);
      setAttendance({ on_duty: a.on_duty, requires_attendance: a.requires_attendance });
    } catch (err) {
      setClockMsg(err instanceof Error ? err.message : "Gagal.");
    } finally {
      setClockBusy(false);
    }
  }

  async function handleSubscribe(pkgId: number) {
    if (!token) return;
    setSubMsg("Menyediakan checkout…");
    try {
      const res = await api.checkout(token, pkgId);
      window.location.assign(res.checkout_url);
    } catch (err) {
      setSubMsg(err instanceof Error ? err.message : "Gagal.");
    }
  }

  function handleLogout() {
    const t = getToken();
    if (t) api.logout(t).catch(() => {});
    clearSession();
    router.replace("/");
  }

  const trialDays = billing?.trial.days_remaining ?? 0;

  return (
    <div className="min-h-dvh bg-[#f4efe4]">
      {/* Top bar */}
      <header className="sticky top-0 z-40 border-b border-stone-200/70 bg-white/90 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
          <div className="flex items-center gap-3">
            <Link href="/" className="text-lg font-extrabold tracking-tight text-ink">
              <span className="mr-1 inline-grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-brand-600 to-emerald-500 align-middle text-xs font-black text-white">S</span>
              sajio<span className="text-brand-600">.</span>
            </Link>
            <div className="hidden sm:block">
              <p className="text-sm font-black leading-tight text-ink">{restaurant?.name ?? "Dashboard"}</p>
              <p className="text-[11px] font-medium leading-tight text-stone-400">{restaurant ? `${restaurant.subdomain}.sajio.my` : (user?.email ?? "")}</p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {/* Package badge + trial chip (owner) */}
            {billing && role === "owner" && (
              <>
                {billing.package ? (
                  <span className="hidden rounded-full bg-stone-800 px-3 py-1 text-xs font-black text-gold-300 sm:inline-block">
                    {billing.package.name}
                  </span>
                ) : billing.needs_subscription ? (
                  <span className="hidden rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-600 sm:inline-block">⛔ Kunci</span>
                ) : (
                  <span className="hidden rounded-full bg-brand-100 px-3 py-1 text-xs font-black text-brand-700 sm:inline-block">
                    TRIAL · Tamat {billing.trial.ends_at ? new Date(billing.trial.ends_at).toLocaleDateString("en-MY", { day: "numeric", month: "short" }) : ""}
                  </span>
                )}
              </>
            )}
            {/* Attendance chip (staff) */}
            {attendance && attendance.requires_attendance && (
              <span className={`hidden rounded-full px-3 py-1 text-xs font-black sm:inline-block ${attendance.on_duty ? "bg-emerald-100 text-emerald-700" : "bg-stone-200 text-stone-600"}`}>
                {attendance.on_duty ? "● BERTUGAS" : "○ BELUM MASUK"}
              </span>
            )}
            <button onClick={handleLogout} className="rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-bold text-stone-600 transition hover:border-red-300 hover:text-red-600">
              Log keluar
            </button>
          </div>
        </div>
      </header>

      {checking ? (
        <div className="mx-auto max-w-6xl px-6 py-24 text-center text-stone-400">Memuatkan…</div>
      ) : (
        <main className="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[240px_1fr]">
          {/* Sidebar */}
          <aside className="space-y-1 rounded-2xl border border-stone-200 bg-white p-3 lg:sticky lg:top-20 lg:self-start">
            {[
              ["📊", "Dashboard", true],
              ["🍽️", "Menu", false],
              ["🪑", "Meja", false],
              ["👥", "Staff", false],
              ["💰", "Bil & Jualan", false],
              ["📦", "Pakej", role === "owner"],
            ].map(([icon, label, active]) => (
              <div key={String(label)} className={`flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-bold ${active ? "bg-brand-700 text-white" : "cursor-not-allowed text-stone-400"}`}>
                <span>{icon}</span>
                <span className="flex-1">{label}</span>
                {active === false && <span className="text-[9px] font-semibold uppercase tracking-wide text-stone-300">soon</span>}
              </div>
            ))}
          </aside>

          {/* Content */}
          <section className="space-y-6">
            {/* Greeting */}
            <div>
              <h1 className="text-2xl font-black tracking-tight text-ink">Selamat datang{user?.name ? `, ${user.name.split(" ")[0]}` : ""} 👋</h1>
              <p className="mt-1 text-sm text-stone-500">
                {restaurant ? `${restaurant.name} · ${restaurant.subdomain}.sajio.my` : ""}
                {billing && !billing.needs_subscription && ` · Percubaan anda tamat dalam ${trialDays} hari`}
              </p>
            </div>

            {/* Clock in/out (staff & manager) */}
            {attendance && attendance.requires_attendance && (
              <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-stone-200 bg-white p-5">
                <div className="flex-1">
                  <p className="font-black text-ink">Kehadiran hari ini</p>
                  <p className="text-sm text-stone-500">
                    {attendance.on_duty ? "Anda sedang bertugas. Jangan lupa clock out selepas shift." : "Clock in bila shift anda bermula untuk mula menerima order."}
                  </p>
                  {clockMsg && <p className="mt-1 text-sm font-semibold text-emerald-600">{clockMsg}</p>}
                </div>
                {attendance.on_duty ? (
                  <button onClick={() => handleClock("out")} disabled={clockBusy} className="rounded-xl bg-stone-800 px-5 py-2.5 text-sm font-black text-white transition hover:bg-stone-700 disabled:opacity-60">
                    Clock out
                  </button>
                ) : (
                  <button onClick={() => handleClock("in")} disabled={clockBusy} className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500 disabled:opacity-60">
                    Clock in
                  </button>
                )}
              </div>
            )}

            {/* Locked banner (owner) */}
            {billing?.needs_subscription && role === "owner" && (
              <div className="rounded-2xl border-2 border-red-200 bg-red-50 p-6">
                <p className="text-lg font-black text-red-700">⛔ Percubaan anda telah tamat</p>
                <p className="mt-1 text-sm text-red-600">Untuk terus menjual, pilih pakej di bawah. Data restoran anda selamat.</p>
              </div>
            )}

            {/* Onboarding checklist (owner, not locked) */}
            {role === "owner" && !billing?.needs_subscription && (
              <div className="rounded-2xl border border-stone-200 bg-white p-6">
                <p className="font-black text-ink">Persediaan restoran</p>
                <p className="text-sm text-stone-500">Lengkapkan langkah berikut untuk mula menerima order.</p>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  {[
                    { t: "Profil & jenama", d: "Logo, alamat, waktu operasi", done: false, soon: true },
                    { t: "Menu & kategori", d: "Tambah makanan & minuman", done: false, soon: true },
                    { t: "Meja & QR", d: "Buat meja untuk dine-in", done: false, soon: true },
                    { t: "Staff & shift", d: "Jemput pekerja & tetapkan shift", done: false, soon: true },
                  ].map((s) => (
                    <div key={s.t} className="flex items-start gap-3 rounded-xl border border-stone-200 p-4">
                      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-stone-100 text-xs font-black text-stone-400">•</span>
                      <div>
                        <p className="text-sm font-black text-ink">{s.t}</p>
                        <p className="text-xs text-stone-500">{s.d}</p>
                        {s.soon && <p className="mt-0.5 text-[10px] font-bold uppercase tracking-wide text-gold-600">Dibuka tidak lama lagi</p>}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Subscribe / plans (owner) */}
            {role === "owner" && billing && (billing.needs_subscription || billing.trial.days_remaining <= 3) && (
              <div className="rounded-2xl border border-stone-200 bg-white p-6">
                <p className="font-black text-ink">Pilih pakej anda</p>
                <p className="text-sm text-stone-500">Langganan bulanan · tiada yuran setup · batalkan bila-bila</p>
                {subMsg && <p className="mt-2 text-sm font-semibold text-stone-600">{subMsg}</p>}
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                  {(plans ?? []).map((p) => (
                    <div key={p.id} className="rounded-xl border border-stone-200 p-5 text-center">
                      <p className="font-black text-ink">{p.name}</p>
                      <p className="mt-1 text-2xl font-black text-brand-700">RM{p.price_monthly}</p>
                      <p className="text-xs text-stone-400">/bulan</p>
                      <button onClick={() => handleSubscribe(p.id)} className="mt-3 w-full rounded-lg bg-brand-700 px-4 py-2 text-sm font-black text-white transition hover:bg-brand-800">
                        Langgan
                      </button>
                    </div>
                  ))}
                  {plans === null && (
                    <button onClick={() => { api.packages().then((r) => setPlans(r.packages)).catch(() => setSubMsg("Gagal memuat pakej.")); }} className="mt-2 text-sm font-bold text-brand-700 hover:underline">
                      Papar pilihan pakej
                    </button>
                  )}
                </div>
              </div>
            )}

            {/* Generic welcome for staff */}
            {role === "staff" && !attendance && (
              <div className="rounded-2xl border border-stone-200 bg-white p-6 text-center">
                <p className="text-2xl">🍽️</p>
                <p className="mt-2 font-black text-ink">Akaun anda sudah sedia</p>
                <p className="text-sm text-stone-500">Modul POS & order akan dibuka dalam fasa seterusnya.</p>
              </div>
            )}
          </section>
        </main>
      )}
    </div>
  );
}
