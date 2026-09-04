"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import AppShell from "../../../components/dashboard/AppShell";
import { rm } from "../../../components/dashboard/money";
import {
  AdvancedReport,
  api,
  CategorySalesRow,
  HourRow,
  MoneySummary,
  ProductSalesRow,
  SalesSeries,
  StaffSalesRow,
} from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";

function todayStr(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}
function daysAgo(n: number): string {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function MoneyCard({ label, value, sub, tone = "ink" }: { label: string; value: string; sub?: string; tone?: "ink" | "rasa" | "emerald" | "red" }) {
  const color = tone === "rasa" ? "text-rasa-600" : tone === "emerald" ? "text-emerald-700" : tone === "red" ? "text-red-600" : "text-ink";
  return (
    <div className="rounded-2xl border border-stone-200 bg-white p-5">
      <p className="text-xs font-black uppercase tracking-wide text-stone-400">{label}</p>
      <p className={`mt-1 text-2xl font-black ${color}`}>{value}</p>
      {sub && <p className="text-xs text-stone-400">{sub}</p>}
    </div>
  );
}

function ReportsPageContent() {
  const token = getToken();
  const [from, setFrom] = useState(() => daysAgo(6));
  const [to, setTo] = useState(() => todayStr());
  const [period, setPeriod] = useState<"daily" | "weekly" | "monthly">("daily");
  const [summary, setSummary] = useState<MoneySummary | null>(null);
  const [series, setSeries] = useState<SalesSeries | null>(null);
  const [products, setProducts] = useState<ProductSalesRow[]>([]);
  const [categories, setCategories] = useState<CategorySalesRow[]>([]);
  const [advancedOk, setAdvancedOk] = useState(false);
  const [advStaff, setAdvStaff] = useState<StaffSalesRow[]>([]);
  const [advHours, setAdvHours] = useState<HourRow[]>([]);
  const [adv, setAdv] = useState<AdvancedReport | null>(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [advancedLocked, setAdvancedLocked] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setErr(null);
    const params = { from, to };
    try {
      const [sum, ser, prod, cats] = await Promise.all([
        api.reportSummary(token, params),
        api.reportSales(token, { ...params, period }),
        api.reportProducts(token, params),
        api.reportCategories(token, params),
      ]);
      setSummary(sum);
      setSeries(ser);
      setProducts(prod.products);
      setCategories(cats.categories);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load reports.");
    } finally {
      setLoading(false);
    }
  }, [token, from, to, period]);

  // Advanced (Premium/Pro) is loaded separately so Basic shows a soft lock.
  const loadAdvanced = useCallback(async () => {
    if (!token) return;
    const params = { from, to };
    try {
      const [staff, hours, adv2] = await Promise.all([
        api.reportStaff(token, params),
        api.reportHours(token, params),
        api.reportAdvanced(token, params),
      ]);
      setAdvStaff(staff.staff);
      setAdvHours(hours.hours);
      setAdv(adv2);
      setAdvancedOk(true);
      setAdvancedLocked(false);
    } catch {
      setAdvancedOk(false);
      setAdvancedLocked(true);
    }
  }, [token, from, to]);

  useEffect(() => {
    // Load reports when the range/period changes.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [load]);

  useEffect(() => {
    // Load advanced reports when the range changes.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadAdvanced();
  }, [loadAdvanced]);

  const maxSeries = useMemo(() => Math.max(...(series?.series.map((s) => Number(s.sales)) ?? [0]), 0.01), [series]);
  const maxHour = useMemo(() => Math.max(...advHours.map((h) => Number(h.sales)), 0.01), [advHours]);

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Reports 📈</h1>
          <p className="text-sm text-stone-500">Sales, money and profit for your range · all in your restaurant&apos;s timezone</p>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <div>
            <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">From</label>
            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={inputCls} max={to} />
          </div>
          <div>
            <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">To</label>
            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={inputCls} min={from} />
          </div>
          <div>
            <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">Sales period</label>
            <select value={period} onChange={(e) => setPeriod(e.target.value as "daily" | "weekly" | "monthly")} className={inputCls}>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
          <button onClick={() => void load()} disabled={loading} className="rounded-xl bg-stone-800 px-4 py-2 text-sm font-black text-white transition hover:bg-stone-700 disabled:opacity-50">
            {loading ? "…" : "Run report"}
          </button>
        </div>
      </div>

      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {/* Money summary */}
      {summary && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <MoneyCard label="Sales (payments)" value={rm(summary.sales.total)} sub={`${summary.sales.payments_count} payments`} tone="rasa" />
            <MoneyCard label="Net orders" value={rm(summary.orders.net)} sub={`${summary.orders.completed_count} completed · gross ${rm(summary.orders.gross)}`} />
            <MoneyCard label="Discounts" value={rm(summary.orders.discounts)} sub={`tax ${rm(summary.orders.tax)}`} />
            <MoneyCard label="Expenses" value={rm(summary.expenses.total)} sub={`${summary.expenses.count} records`} />
            <div className="rounded-2xl border-2 p-5" style={{ borderColor: Number(summary.net_position) < 0 ? "#fecaca" : "#bbf7d0", backgroundColor: Number(summary.net_position) < 0 ? "#fef2f2" : "#f0fdf4" }}>
              <p className="text-xs font-black uppercase tracking-wide text-stone-500">Net position</p>
              <p className={`mt-1 text-2xl font-black ${Number(summary.net_position) < 0 ? "text-red-600" : "text-emerald-700"}`}>{rm(summary.net_position)}</p>
              <p className="text-xs text-stone-400">Sales − Expenses</p>
            </div>
          </div>

          {/* By method + gross/discounts */}
          <div className="grid gap-3 lg:grid-cols-2">
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">Payment breakdown</p>
              {summary.sales.by_method.length === 0 ? (
                <p className="text-sm text-stone-400">No payments in this range.</p>
              ) : (
                <div className="grid gap-1.5 sm:grid-cols-2">
                  {summary.sales.by_method.map((m) => {
                    const max = Math.max(...summary.sales.by_method.map((x) => Number(x.amount)), 0.01);
                    return (
                      <div key={m.method} className="rounded-xl border border-stone-200 px-3 py-2">
                        <div className="flex items-center justify-between text-sm">
                          <span className="font-bold text-ink">{m.method_label}</span>
                          <span className="font-black text-rasa-600">{rm(m.amount)}</span>
                        </div>
                        <div className="mt-1.5 h-1.5 rounded-full bg-stone-100">
                          <div className="h-1.5 rounded-full bg-rasa-500" style={{ width: `${(Number(m.amount) / max) * 100}%` }} />
                        </div>
                        <p className="mt-1 text-[11px] text-stone-400">{m.count} payment{m.count === 1 ? "" : "s"}</p>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Sales series chart */}
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">
                Sales trend · {series?.period ?? ""}
              </p>
              {series && series.series.length > 0 && (
                <div className="flex h-36 items-end gap-1.5">
                  {series.series.map((s) => (
                    <div key={s.label} className="flex min-w-0 flex-1 flex-col items-center gap-1" title={`${s.label}: ${rm(s.sales)}`}>
                      <div className="flex w-full flex-1 items-end">
                        <div
                          className="w-full rounded-t-md bg-rasa-500/80 transition hover:bg-rasa-500"
                          style={{ height: `${Math.max(4, (Number(s.sales) / maxSeries) * 100)}%` }}
                        />
                      </div>
                      <p className="max-w-full truncate text-[9px] font-black text-stone-400">{s.label}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Products & categories */}
          <div className="grid gap-3 lg:grid-cols-2">
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">Top products</p>
              {products.length === 0 ? (
                <p className="text-sm text-stone-400">No completed sales in this range.</p>
              ) : (
                <div className="divide-y divide-stone-100">
                  {products.slice(0, 15).map((p) => (
                    <div key={p.product_id ?? p.name} className="flex items-center gap-2 py-2">
                      <span className="min-w-0 flex-1 truncate text-sm font-bold text-ink">{p.name}</span>
                      <span className="text-xs text-stone-400">{p.quantity}×</span>
                      <span className="w-20 text-right text-sm font-black text-rasa-600">{rm(p.revenue)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">By category</p>
              {categories.length === 0 ? (
                <p className="text-sm text-stone-400">No completed sales in this range.</p>
              ) : (
                <div className="divide-y divide-stone-100">
                  {categories.map((c) => {
                    const max = Math.max(...categories.map((x) => Number(x.revenue)), 0.01);
                    return (
                      <div key={c.category} className="py-2">
                        <div className="flex items-center justify-between text-sm">
                          <span className="font-bold text-ink">{c.category}</span>
                          <span className="font-black text-rasa-600">{rm(c.revenue)}</span>
                        </div>
                        <div className="mt-1 h-1.5 rounded-full bg-stone-100">
                          <div className="h-1.5 rounded-full bg-rasa-500" style={{ width: `${(Number(c.revenue) / max) * 100}%` }} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </>
      )}

      {/* Advanced (Premium/Pro) */}
      {advancedLocked && (
        <div className="rounded-2xl border border-stone-200 bg-white p-6 text-center">
          <p className="text-2xl">🔒</p>
          <p className="mt-2 font-black text-ink">Advanced reports</p>
          <p className="text-sm text-stone-500">Staff sales, hourly sales, discounts & profit summaries are available on the Premium and Pro plans.</p>
        </div>
      )}

      {advancedOk && adv && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MoneyCard label="Staff & hourly insights" value="Active" sub="Premium/Pro reports below" tone="emerald" />
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="text-xs font-black uppercase tracking-wide text-stone-400">Discounts</p>
              <p className="mt-1 text-2xl font-black text-ink">{rm(adv.discounts.total_discount)}</p>
              <p className="text-xs text-stone-400">{adv.discounts.discounted_orders} discounted orders</p>
            </div>
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="text-xs font-black uppercase tracking-wide text-stone-400">Void / cancelled</p>
              <p className="mt-1 text-2xl font-black text-stone-800">{adv.void_refunds.count} orders</p>
              <p className="text-xs text-stone-400">{rm(adv.void_refunds.amount)} not collected</p>
            </div>
            <div className="rounded-2xl border-2 border-rasa-200 bg-rasa-50 p-5">
              <p className="text-xs font-black uppercase tracking-wide text-rasa-400">Net profit</p>
              <p className="mt-1 text-2xl font-black text-rasa-600">{rm(adv.profit)}</p>
              <p className="text-xs text-rasa-400">{rm(adv.net_sales)} sales − {rm(adv.total_expenses)} expenses</p>
            </div>
          </div>

          <div className="grid gap-3 lg:grid-cols-2">
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">Sales by staff</p>
              {advStaff.length === 0 ? (
                <p className="text-sm text-stone-400">No completed orders in this range.</p>
              ) : (
                <div className="divide-y divide-stone-100">
                  {advStaff.map((s) => (
                    <div key={s.staff_id ?? "anon"} className="flex items-center gap-2 py-2">
                      <span className="min-w-0 flex-1 truncate text-sm font-bold text-ink">{s.name}</span>
                      <span className="text-xs text-stone-400">{s.orders} order{s.orders === 1 ? "" : "s"}</span>
                      <span className="w-20 text-right text-sm font-black text-rasa-600">{rm(s.total)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="rounded-2xl border border-stone-200 bg-white p-5">
              <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-400">Sales by hour</p>
              {maxHour > 0 ? (
                <div className="flex h-28 items-end gap-[3px]">
                  {advHours.map((h) => (
                    <div key={h.hour} className="flex min-w-0 flex-1 flex-col items-center gap-0.5" title={`${h.label}: ${rm(h.sales)} (${h.count})`}>
                      <div className="flex w-full flex-1 items-end">
                        <div
                          className={`w-full rounded-t ${Number(h.sales) > 0 ? "bg-rasa-500/70" : "bg-stone-100"}`}
                          style={{ height: `${Number(h.sales) > 0 ? Math.max(6, (Number(h.sales) / maxHour) * 100) : 6}%` }}
                        />
                      </div>
                      {h.hour % 4 === 0 && <p className="text-[8px] font-black text-stone-400">{h.hour}</p>}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-stone-400">No payments in this range.</p>
              )}
              <p className="mt-2 text-[10px] text-stone-400">Sales each hour of the day (local time).</p>
            </div>
          </div>
        </>
      )}
    </>
  );
}

export default function ReportsPage() {
  return (
    <AppShell active="reports">
      <ReportsPageContent />
    </AppShell>
  );
}
