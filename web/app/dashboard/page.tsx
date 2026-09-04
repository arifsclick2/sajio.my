"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../components/dashboard/AppShell";
import { rm, timeOnly } from "../../components/dashboard/money";
import { api, MoneyDashboard } from "../../lib/api";
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
  const [money, setMoney] = useState<MoneyDashboard | null>(null);
  const [moneyErr, setMoneyErr] = useState<string | null>(null);

  const canManage = role === "owner" || role === "manager";

  const loadMoney = useCallback(async () => {
    if (!token) return;
    try {
      const r = await api.reportDashboard(token);
      setMoney(r);
      setMoneyErr(null);
    } catch {
      setMoneyErr("Could not load today's numbers.");
    }
  }, [token]);

  useEffect(() => {
    if (canManage) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void loadMoney();
    }
  }, [canManage, loadMoney]);

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
      {canManage ? (
        <>
          {/* Today's money at a glance (§20) */}
          <div className="space-y-4">
            {money && !moneyErr && (
              <>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  <div className="rounded-2xl border border-stone-200 bg-white p-5">
                    <p className="text-xs font-black uppercase tracking-wide text-stone-400">Today&apos;s sales</p>
                    <p className="mt-1 text-3xl font-black text-rasa-600">{rm(money.today.sales)}</p>
                    <p className="text-xs text-stone-400">{money.today.payments_count} payment{money.today.payments_count === 1 ? "" : "s"} · {money.today.completed_count} order{money.today.completed_count === 1 ? "" : "s"} completed</p>
                  </div>
                  <div className="rounded-2xl border border-stone-200 bg-white p-5">
                    <p className="text-xs font-black uppercase tracking-wide text-stone-400">Expenses today</p>
                    <p className="mt-1 text-3xl font-black text-stone-800">{rm(money.today.expenses)}</p>
                    <p className="text-xs text-stone-400">{money.today.expenses_count} record{money.today.expenses_count === 1 ? "" : "s"}</p>
                  </div>
                  <div className={`rounded-2xl border p-5 ${Number(money.net_position) < 0 ? "border-red-200 bg-red-50" : "border-emerald-200 bg-emerald-50"}`}>
                    <p className="text-xs font-black uppercase tracking-wide text-stone-400">Net position today</p>
                    <p className={`mt-1 text-3xl font-black ${Number(money.net_position) < 0 ? "text-red-600" : "text-emerald-700"}`}>{rm(money.net_position)}</p>
                    <p className="text-xs text-stone-400">Sales − Expenses</p>
                  </div>
                  <div className="rounded-2xl border border-stone-200 bg-white p-5">
                    <p className="text-xs font-black uppercase tracking-wide text-stone-400">Live right now</p>
                    <div className="mt-2 flex items-center gap-4">
                      <div>
                        <p className="text-2xl font-black text-ink">{money.live.active_tables}</p>
                        <p className="text-[11px] font-bold text-stone-400">tables open</p>
                      </div>
                      <div className="h-8 w-px bg-stone-100" />
                      <div>
                        <p className="text-2xl font-black text-rasa-600">{money.live.pending_orders}</p>
                        <p className="text-[11px] font-bold text-stone-400">orders pending</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                  {/* Sales trend 7 days */}
                  <div className="rounded-2xl border border-stone-200 bg-white p-5">
                    <div className="flex items-center justify-between">
                      <p className="text-sm font-black text-ink">Sales — last 7 days</p>
                      <a href="/dashboard/reports" className="text-xs font-black text-rasa-600 hover:underline">
                        Full reports →
                      </a>
                    </div>
                    <div className="mt-4 flex h-32 items-end gap-2">
                      {money.trend.map((t) => {
                        const max = Math.max(...money.trend.map((x) => Number(x.sales)), 0.01);
                        const h = Math.max(4, (Number(t.sales) / max) * 100);
                        return (
                          <div key={t.date} className="flex flex-1 flex-col items-center gap-1" title={`${t.date}: ${rm(t.sales)}`}>
                            <div className="flex w-full flex-1 items-end">
                              <div className="w-full rounded-t-md bg-rasa-500/80 transition hover:bg-rasa-500" style={{ height: `${h}%` }} />
                            </div>
                            <p className="text-[10px] font-black text-stone-400">{t.label}</p>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Recent orders */}
                  <div className="rounded-2xl border border-stone-200 bg-white p-5">
                    <div className="flex items-center justify-between">
                      <p className="text-sm font-black text-ink">Recent orders</p>
                      <a href="/dashboard/pos" className="text-xs font-black text-rasa-600 hover:underline">
                        Floor →
                      </a>
                    </div>
                    <div className="mt-3 space-y-1.5">
                      {money.recent_orders.length === 0 && <p className="py-6 text-center text-sm text-stone-400">No orders yet today.</p>}
                      {money.recent_orders.slice(0, 6).map((o) => (
                        <div key={o.id} className="flex items-center gap-3 rounded-xl border border-stone-100 px-3 py-2">
                          <p className="font-black text-ink">{o.order_no}</p>
                          {o.table && <span className="text-xs font-bold text-stone-400">T{o.table.number}</span>}
                          <span className="ml-auto text-xs font-black text-stone-500">{timeOnly(o.created_at)}</span>
                          <span className="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-black text-stone-500">{o.status_label}</span>
                          <p className="w-16 text-right text-sm font-black text-rasa-600">{rm(o.total)}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </>
            )}
            {moneyErr && !money && <p className="text-sm font-semibold text-stone-400">{moneyErr}</p>}
          </div>
        </>
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
