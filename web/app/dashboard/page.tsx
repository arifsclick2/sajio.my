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
  const token = getToken();
  const [clockBusy, setClockBusy] = useState(false);
  const [clockMsg, setClockMsg] = useState<string | null>(null);
  const [plans, setPlans] = useState<Pkg[] | null>(null);
  const [plansShown, setPlansShown] = useState(false);
  const [subMsg, setSubMsg] = useState<string | null>(null);

  async function handleClock(action: "in" | "out") {
    if (!token) return;
    setClockBusy(true);
    setClockMsg(null);
    try {
      if (action === "in") {
        await clockIn();
        setClockMsg("Clock in successful. Have a great shift!");
      } else {
        await clockOut();
        setClockMsg("Clock out successful. See you next shift!");
      }
    } catch (err) {
      setClockMsg(err instanceof Error ? err.message : "Failed.");
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
      setSubMsg(err instanceof Error ? err.message : "Failed to load plans.");
    }
  }

  async function handleSubscribe(pkgId: number) {
    if (!token) return;
    setSubMsg("Preparing checkout…");
    try {
      const res = await api.checkout(token, pkgId);
      window.location.assign(res.checkout_url);
    } catch (err) {
      setSubMsg(err instanceof Error ? err.message : "Failed.");
    }
  }

  const trialDays = billing?.trial.days_remaining ?? 0;
  const firstName = user?.name?.split(" ")[0] ?? "";

  return (
    <>
      {/* Greeting */}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Welcome{firstName ? `, ${firstName}` : ""} 👋</h1>
          <p className="mt-1 text-sm text-stone-500">
            {restaurant ? `${restaurant.name} · ${restaurant.subdomain}.sajio.my` : ""}
            {billing && !billing.needs_subscription && ` · Trial ends in ${trialDays} day${trialDays === 1 ? "" : "s"}`}
          </p>
        </div>
        {attendance?.requires_attendance && (
          <div className="text-right">
            <p className={`text-sm font-black ${attendance.on_duty ? "text-emerald-600" : "text-stone-400"}`}>
              {attendance.on_duty ? "● On duty" : "○ Not clocked in"}
            </p>
            {clockMsg && <p className="text-xs font-semibold text-emerald-600">{clockMsg}</p>}
          </div>
        )}
      </div>

      {/* Quick actions — owner/manager: full suite. Staff: waiter order station. */}
      {role === "owner" || role === "manager" ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <a href="/dashboard/pos" className="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-rasa-300 hover:shadow-lg hover:shadow-rasa-500/10">
            <p className="text-2xl">🧾</p>
            <p className="mt-2 font-black text-ink group-hover:text-rasa-600">POS</p>
            <p className="text-xs text-stone-500">Take orders & receive payments</p>
          </a>
          <a href="/dashboard/menu" className="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-rasa-300 hover:shadow-lg hover:shadow-rasa-500/10">
            <p className="text-2xl">🍽️</p>
            <p className="mt-2 font-black text-ink group-hover:text-rasa-600">Menu</p>
            <p className="text-xs text-stone-500">Categories & products</p>
          </a>
          <a href="/dashboard/tables" className="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-rasa-300 hover:shadow-lg hover:shadow-rasa-500/10">
            <p className="text-2xl">🪑</p>
            <p className="mt-2 font-black text-ink group-hover:text-rasa-600">Tables</p>
            <p className="text-xs text-stone-500">Manage tables & sessions</p>
          </a>
          <a href="/dashboard/sales" className="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-rasa-300 hover:shadow-lg hover:shadow-rasa-500/10">
            <p className="text-2xl">💰</p>
            <p className="mt-2 font-black text-ink group-hover:text-rasa-600">Sales</p>
            <p className="text-xs text-stone-500">Receipts & daily sales</p>
          </a>
        </div>
      ) : (
        /* Staff / waiter — their whole job is taking orders at tables. */
        <div className="rounded-2xl border border-rasa-200 bg-white p-6 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="min-w-0">
              <p className="text-lg font-black text-ink">Take orders 🧾</p>
              <p className="mt-1 text-sm text-stone-500">
                {attendance?.on_duty
                  ? "You're on duty — start serving your tables."
                  : "Clock in when your shift starts, then start serving your tables."}
              </p>
            </div>
            <a
              href="/dashboard/pos"
              className="rounded-xl bg-rasa-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-rasa-600/25 transition hover:bg-rasa-700"
            >
              Open order screen →
            </a>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-3">
            {[
              { n: "1", t: "Pick a table", d: "Tap the table your customer is at (or Takeaway)." },
              { n: "2", t: "Add their items", d: "Tap the food & drinks — they land in the order on the right." },
              { n: "3", t: "Send to kitchen", d: "It goes straight to the kitchen — follow it on the Floor tab." },
            ].map((s) => (
              <div key={s.n} className="flex items-start gap-3 rounded-xl border border-stone-200 bg-[#fdf8f6] p-3">
                <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-rasa-500 text-xs font-black text-white">{s.n}</span>
                <div>
                  <p className="text-sm font-black text-ink">{s.t}</p>
                  <p className="text-xs leading-snug text-stone-500">{s.d}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Clock in/out (staff & manager) */}
      {attendance?.requires_attendance && (
        <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-stone-200 bg-white p-5">
          <div className="min-w-0 flex-1">
            <p className="font-black text-ink">Today&apos;s attendance</p>
            <p className="text-sm text-stone-500">
              {attendance.on_duty
                ? "You are on duty — take orders from the Order screen."
                : "Clock in when your shift starts to begin taking orders."}
            </p>
          </div>
          <button
            onClick={() => handleClock(attendance.on_duty ? "out" : "in")}
            disabled={clockBusy}
            className={`rounded-xl px-6 py-3 text-sm font-black text-white shadow-lg transition hover:opacity-90 disabled:opacity-50 ${
              attendance.on_duty ? "bg-ink" : "bg-emerald-600 shadow-emerald-600/25"
            }`}
          >
            {clockBusy ? "…" : attendance.on_duty ? "Clock out" : "Clock in"}
          </button>
        </div>
      )}

      {/* Locked banner (owner) */}
      {billing?.needs_subscription && role === "owner" && (
        <div className="rounded-2xl border-2 border-rasa-200 bg-rasa-50 p-6">
          <p className="text-lg font-black text-rasa-600">Your trial has ended</p>
          <p className="mt-1 text-sm text-rasa-700">To keep selling, choose a plan below. Your restaurant data is safe.</p>
        </div>
      )}

      {/* Onboarding checklist (owner, not locked) */}
      {role === "owner" && !billing?.needs_subscription && (
        <div className="rounded-2xl border border-stone-200 bg-white p-6">
          <p className="font-black text-ink">Restaurant setup</p>
          <p className="text-sm text-stone-500">Complete these steps to start taking orders.</p>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {[
              { t: "Menu & categories", d: "Add your food & drinks", href: "/dashboard/menu" },
              { t: "Tables", d: "Create tables for dine-in", href: "/dashboard/tables" },
              { t: "POS", d: "Start taking orders & payments", href: "/dashboard/pos" },
              { t: "Sales", d: "View receipts & daily sales", href: "/dashboard/sales" },
            ].map((s) => (
              <a key={s.t} href={s.href} className="flex items-start gap-3 rounded-xl border border-stone-200 p-4 transition hover:border-rasa-300">
                <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-rasa-50 text-xs font-black text-rasa-500 ring-1 ring-rasa-100">→</span>
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
        <div className="rounded-2xl border border-stone-200 bg-white p-6" id="plans">
          <p className="font-black text-ink">Choose your plan</p>
          <p className="text-sm text-stone-500">Monthly subscription · no setup fee · cancel anytime</p>
          {subMsg && <p className="mt-2 text-sm font-semibold text-stone-600">{subMsg}</p>}
          <div className="mt-4 grid gap-3 md:grid-cols-3">
            {(plans ?? []).map((p) => (
              <div key={p.id} className="rounded-xl border border-stone-200 p-5 text-center">
                <p className="font-black text-ink">{p.name}</p>
                <p className="mt-1 text-2xl font-black text-rasa-500">RM{Number(p.price_monthly).toFixed(0)}</p>
                <p className="text-xs text-stone-400">/month</p>
                <button onClick={() => handleSubscribe(p.id)} className="mt-3 w-full rounded-lg bg-rasa-500 px-4 py-2 text-sm font-black text-white transition hover:bg-rasa-600">
                  Subscribe
                </button>
              </div>
            ))}
            {!plansShown && plans === null && (
              <button onClick={loadPlans} className="mt-2 rounded-lg border border-rasa-200 bg-rasa-50 px-4 py-3 text-sm font-black text-rasa-600 transition hover:bg-rasa-100">
                Show plan options
              </button>
            )}
          </div>
        </div>
      )}

      {/* Generic welcome for staff */}
      {role === "staff" && !attendance && (
        <div className="rounded-2xl border border-stone-200 bg-white p-6 text-center">
          <p className="text-2xl">🍽️</p>
          <p className="mt-2 font-black text-ink">Your account is ready</p>
          <p className="text-sm text-stone-500">
            Ask your owner to add menu items and tables so you can start taking orders on the Order screen.
          </p>
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
