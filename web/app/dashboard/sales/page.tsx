"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell from "../../../components/dashboard/AppShell";
import { rm, timeOnly } from "../../../components/dashboard/money";
import { printReceipt } from "../../../components/dashboard/printReceipt";
import { api, PaymentInfo, PaymentsResponse } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100";

function SalesPageContent() {
  const token = getToken();

  const [date, setDate] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  });
  const [methodFilter, setMethodFilter] = useState("");
  const [data, setData] = useState<PaymentsResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setErr(null);
    try {
      const r = await api.paymentsIndex(token, { date, method: methodFilter || undefined, per_page: 100 });
      setData(r);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal memuat jualan.");
    } finally {
      setLoading(false);
    }
  }, [token, date, methodFilter]);

  useEffect(() => {
    // Load sales for the selected date when it changes.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [load]);

  async function printPayment(p: PaymentInfo) {
    if (!token) return;
    try {
      if (p.order_id) {
        const r = await api.orderReceipt(token, p.order_id);
        printReceipt(r.receipt);
      } else if (p.table_session_id) {
        const r = await api.sessionReceipt(token, p.table_session_id);
        printReceipt(r.receipt);
      }
    } catch {
      /* receipt may be unavailable (e.g. session open) — ignore */
    }
  }

  const summary = data?.summary;

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Bil &amp; Jualan 💰</h1>
          <p className="text-sm text-stone-500">Pembayaran direkod pada masa terima — bukan pemproses bayaran.</p>
        </div>
        <div className="flex items-center gap-2">
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={inputCls} max={new Date().toISOString().slice(0, 10)} />
          <button onClick={() => void load()} disabled={loading} className="rounded-xl bg-stone-800 px-4 py-2 text-sm font-black text-white transition hover:bg-stone-700 disabled:opacity-50">
            {loading ? "…" : "Muat semula"}
          </button>
        </div>
      </div>

      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {/* Summary */}
      {summary && (
        <div className="grid gap-3 sm:grid-cols-3">
          <div className="rounded-2xl border border-stone-200 bg-white p-5">
            <p className="text-xs font-black uppercase tracking-wide text-stone-400">Jumlah jualan</p>
            <p className="mt-1 text-3xl font-black text-brand-700">{rm(summary.total_amount)}</p>
            <p className="text-xs text-stone-400">{summary.count} pembayaran</p>
          </div>
          <div className="rounded-2xl border border-stone-200 bg-white p-5 sm:col-span-2">
            <p className="text-xs font-black uppercase tracking-wide text-stone-400">Mengikut kaedah</p>
            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
              {summary.by_method.map((m) => (
                <button
                  key={m.method}
                  onClick={() => setMethodFilter(methodFilter === m.method ? "" : m.method)}
                  className={`flex items-center justify-between rounded-xl border px-3 py-2 text-left transition ${
                    methodFilter === m.method ? "border-brand-600 bg-brand-50 ring-2 ring-brand-200" : "border-stone-200 hover:border-brand-300"
                  }`}
                >
                  <span className="text-sm font-bold text-ink">{m.method_label}</span>
                  <span className="text-sm font-black text-brand-700">
                    {rm(m.amount)} <span className="text-[10px] font-bold text-stone-400">× {m.count}</span>
                  </span>
                </button>
              ))}
              {summary.by_method.length === 0 && <p className="col-span-full text-sm text-stone-400">Tiada pembayaran pada tarikh ini.</p>}
            </div>
          </div>
        </div>
      )}

      {/* List */}
      <div className="rounded-2xl border border-stone-200 bg-white">
        {!data || data.data.length === 0 ? (
          <p className="p-8 text-center text-sm text-stone-400">Tiada pembayaran direkod.</p>
        ) : (
          <div className="divide-y divide-stone-100">
            {data.data.map((p) => (
              <div key={p.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                <div className="min-w-[64px]">
                  <p className="text-sm font-black text-ink">{rm(p.amount)}</p>
                  <p className="text-[11px] text-stone-400">{timeOnly(p.paid_at)}</p>
                </div>
                <span className="rounded-full bg-stone-100 px-2.5 py-1 text-[11px] font-black text-stone-600">{p.method_label}</span>
                {p.reference && <span className="truncate text-xs text-stone-400">Ref: {p.reference}</span>}
                <div className="ml-auto flex items-center gap-2">
                  {(p.order_id || p.table_session_id) && (
                    <button onClick={() => void printPayment(p)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 transition hover:border-brand-300 hover:text-brand-700">
                      🖨️ Resit
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

export default function SalesPage() {
  return (
    <AppShell active="sales">
      <SalesPageContent />
    </AppShell>
  );
}
