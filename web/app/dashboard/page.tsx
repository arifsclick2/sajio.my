"use client";

import { useState } from "react";
import AppShell, { useDashboard } from "../../components/dashboard/AppShell";
import { api } from "../../lib/api";
import { getToken } from "../../lib/session";

interface Pkg {
  id: number;
  name: string;
  slug: string;
  description?: string;
  price_monthly: string;
}

function HomeContent() {
  const { user, role, restaurant, billing, attendance, clockIn, clockOut } = useDashboard();
  const [clockBusy, setClockBusy] = useState(false);
  const [clockMsg, setClockMsg] = useState<string | null>(null);
  const [plans, setPlans] = useState<Pkg[] | null>(null);
  const [plansShown, setPlansShown] = useState(false);
  const [subMsg, setSubMsg] = useState<string | null>(null);

  const token = getToken();

  async function handleClock(action: "in" | "out") {
    if (!token) return;
    setClockBusy(true);
    setClockMsg(null);
    try {
      if (action === "in") {
        await clockIn();
        setClockMsg("Clock in berjaya. Selamat bertugas!");
      } else {
        await clockOut();
        setClockMsg("Clock out berjaya. Jumpa lagi!");
      }
    } catch (err) {
      setClockMsg(err instanceof Error ? err.message : "Gagal.");
    } finally {
      setClockBusy(false);
    }
  }

  async function loadPlans() {
    if (!token) return;
    setPlansShown(true);
    try {
      const r = await api.packages();
      setPlans(r.packages);
    } catch (err) {
      setSubMsg(err instanceof Error ? err.message : "Gagal memuat pakej.");
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

  const trialDays = billing?.trial.days_remaining ?? 0;
  const firstName = user?.name?.split(" ")[0] ?? "";

  return (
    <>
      {/* Greeting */}
      <div>
        <h1 className="text-2xl font-black tracking-tight text-ink">Selamat datang{firstName ? `, ${firstName}` : ""} 👋</h1>
        <p className="mt-1 text-sm text-stone-500">
          {restaurant ? `${restaurant.name} · ${restaurant.subdomain}.sajio.my` : ""}
          {billing && !billing.needs_subscription && ` · Percubaan anda tamat dalam ${trialDays} hari`}
        </p>
      </div>

      {/* Quick actions */}
      <div className="grid gap-3 sm:grid-cols-3">
        <a href="/dashboard/pos" className="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">
          <p className="text-2xl">🧾</p>
          <p className="mt-2 font-black text-ink">POS</p>
          <p className="text-xs text-stone-500">Ambil order & terima bayaran</p>
        </a>
        {role === "owner" || role === "manager" ? (
          <>
            <a href="/dashboard/menu" className="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">
              <p className="text-2xl">🍽️</p>
              <p className="mt-2 font-black text-ink">Menu</p>
              <p className="text-xs text-stone-500">Kategori & produk</p>
            </a>
            <a href="/dashboard/meja" className="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">
              <p className="text-2xl">🪑</p>
              <p className="mt-2 font-black text-ink">Meja</p>
              <p className="text-xs text-stone-500">Urus meja & sesi</p>
            </a>
          </>
        ) : (
          <a href="/dashboard/sales" className="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md">
            <p className="text-2xl">💰</p>
            <p className="mt-2 font-black text-ink">Jualan</p>
            <p className="text-xs text-stone-500">Lihat jualan hari ini</p>
          </a>
        )}
      </div>

      {/* Clock in/out (staff & manager) */}
      {attendance?.requires_attendance && (
        <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-stone-200 bg-white p-5">
          <div className="min-w-0 flex-1">
            <p className="font-black text-ink">Kehadiran hari ini</p>
            <p className="text-sm text-stone-500">
              {attendance.on_duty
                ? "Anda sedang bertugas. Anda boleh menerima order di POS."
                : "Clock in bila shift anda bermula untuk mula menerima order."}
            </p>
            {(clockMsg || attendance.on_duty) && (
              <p className={`mt-1 text-sm font-semibold ${attendance.on_duty ? "text-emerald-600" : "text-stone-500"}`}>
                {clockMsg ?? "● BERTUGAS"}
              </p>
            )}
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
              { t: "Menu & kategori", d: "Tambah makanan & minuman", href: "/dashboard/menu" },
              { t: "Meja", d: "Buat meja untuk dine-in", href: "/dashboard/meja" },
              { t: "POS", d: "Mula terima order & bayaran", href: "/dashboard/pos" },
              { t: "Bil & Jualan", d: "Papar jualan & resit", href: "/dashboard/sales" },
            ].map((s) => (
              <a key={s.t} href={s.href} className="flex items-start gap-3 rounded-xl border border-stone-200 p-4 transition hover:border-brand-300">
                <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-black text-brand-700">→</span>
                <div>
                  <p className="text-sm font-black text-ink">{s.t}</p>
                  <p className="text-xs text-stone-500">{s.d}</p>
                </div>
              </a>
            ))}
          </div>
        </div>
      )}

      {/* Subscribe / plans (owner) */}
      {role === "owner" && billing && (billing.needs_subscription || billing.trial.days_remaining <= 3) && (
        <div className="rounded-2xl border border-stone-200 bg-white p-6" id="pakej">
          <p className="font-black text-ink">Pilih pakej anda</p>
          <p className="text-sm text-stone-500">Langganan bulanan · tiada yuran setup · batalkan bila-bila</p>
          {subMsg && <p className="mt-2 text-sm font-semibold text-stone-600">{subMsg}</p>}
          <div className="mt-4 grid gap-3 md:grid-cols-3">
            {(plans ?? []).map((p) => (
              <div key={p.id} className="rounded-xl border border-stone-200 p-5 text-center">
                <p className="font-black text-ink">{p.name}</p>
                <p className="mt-1 text-2xl font-black text-brand-700">RM{Number(p.price_monthly).toFixed(2)}</p>
                <p className="text-xs text-stone-400">/bulan</p>
                <button onClick={() => handleSubscribe(p.id)} className="mt-3 w-full rounded-lg bg-brand-700 px-4 py-2 text-sm font-black text-white transition hover:bg-brand-800">
                  Langgan
                </button>
              </div>
            ))}
            {!plansShown && plans === null && (
              <button onClick={loadPlans} className="mt-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-black text-brand-700 transition hover:bg-brand-100">
                Papar pilihan pakej
              </button>
            )}
          </div>
        </div>
      )}
    </>
  );
}

export default function DashboardPage() {
  return (
    <AppShell active="home">
      <HomeContent />
    </AppShell>
  );
}
