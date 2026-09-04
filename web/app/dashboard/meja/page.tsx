"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { rm, timeOnly } from "../../../components/dashboard/money";
import { printReceipt } from "../../../components/dashboard/printReceipt";
import { api, BillResponse, SessionInfo, TableInfo } from "../../../lib/api";

const inputCls =
  "rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100";
const btnPrimary = "rounded-xl bg-brand-700 px-4 py-2 text-sm font-black text-white transition hover:bg-brand-800 disabled:opacity-50";
const METHODS = [
  { m: "cash", label: "💵 Tunai" },
  { m: "card", label: "💳 Kad" },
  { m: "qr", label: "📱 QR / DuitNow" },
  { m: "other", label: "🔘 Lain-lain" },
];

function MejaPageContent() {
  const { role } = useDashboard();
  const token = typeof window !== "undefined" ? window.localStorage.getItem("sajio_token") : null;

  const [tables, setTables] = useState<TableInfo[]>([]);
  const [sessions, setSessions] = useState<SessionInfo[]>([]);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const [addOpen, setAddOpen] = useState(false);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [newNum, setNewNum] = useState("");
  const [bulkFrom, setBulkFrom] = useState("1");
  const [bulkTo, setBulkTo] = useState("10");

  // settle dialog
  const [settle, setSettle] = useState<null | { session: SessionInfo; bill: BillResponse | null; method: string; busy: boolean }>(null);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [t, s] = await Promise.all([api.tables(token), api.openSessions(token)]);
      setTables(t.tables);
      setSessions(s.sessions);
      setErr(null);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal memuat meja.");
    }
  }, [token]);

  useEffect(() => {
    if (role === "owner" || role === "manager") {
      // Initial tables/sessions fetch on mount.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [load, role]);

  function flashOk(text: string) {
    setMsg(text);
    setTimeout(() => setMsg(null), 2500);
  }

  const sessionFor = (tableId: number) => sessions.find((s) => s.table?.id === tableId) ?? null;

  async function addTable(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    try {
      await api.createTable(token, { number: newNum.trim() });
      setNewNum("");
      setAddOpen(false);
      flashOk("Meja ditambah.");
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Gagal.");
    }
  }

  async function bulkAdd(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    const from = Number(bulkFrom);
    const to = Number(bulkTo);
    if (!from || !to || to < from) {
      setErr("Julat tidak sah.");
      return;
    }
    try {
      const r = await api.bulkTables(token, { from, to });
      setBulkOpen(false);
      flashOk(`${r.count} meja ditambah.`);
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Gagal.");
    }
  }

  async function removeTable(t: TableInfo) {
    if (!token) return;
    if (!window.confirm(`Buang meja ${t.number}?`)) return;
    try {
      await api.deleteTable(token, t.id);
      flashOk("Meja dibuang.");
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal.");
    }
  }

  async function regenerate(t: TableInfo) {
    if (!token) return;
    if (!window.confirm(`Tukar token QR meja ${t.number}? Kad QR lama tidak akan berfungsi lagi.`)) return;
    try {
      const r = await api.regenerateTableToken(token, t.id);
      setErr(null);
      flashOk(`Token baru meja ${r.table.number}: ${r.table.public_token}`);
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal.");
    }
  }

  async function openSettleDialog(tableId: number, session: SessionInfo) {
    if (!token) return;
    setSettle({ session, bill: null, method: "", busy: false });
    try {
      const bill = await api.tableCurrent(token, tableId);
      setSettle((s) => (s ? { ...s, bill } : s));
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal memuat bil.");
    }
  }

  async function confirmSettle() {
    const s = settle;
    if (!token || !s || !s.method) return;
    setSettle({ ...s, busy: true });
    try {
      await api.settleSession(token, s.session.id, { method: s.method });
      setSettle(null);
      flashOk("Bil diselesaikan. Meja kosong semula.");
      void load();
      // Print session receipt
      try {
        const receipt = await api.sessionReceipt(token, s.session.id);
        printReceipt(receipt.receipt);
      } catch {
        /* printing is best-effort */
      }
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal menyelesaikan bil.");
      setSettle((prev) => (prev ? { ...prev, busy: false } : prev));
    }
  }

  const billLines = settle?.bill?.orders ?? [];

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Meja 🪑</h1>
          <p className="text-sm text-stone-500">
            {tables.length} meja · {sessions.length} sesi terbuka
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => setBulkOpen(true)} className="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-black text-stone-700 transition hover:border-brand-300 hover:text-brand-700">
            + Banyak (1–N)
          </button>
          <button onClick={() => setAddOpen(true)} className={btnPrimary}>+ Meja</button>
        </div>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {addOpen && (
        <form onSubmit={addTable} className="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4">
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Nombor / nama meja</label>
            <input required value={newNum} onChange={(e) => setNewNum(e.target.value)} className={inputCls} placeholder="cth: 5 atau Luar" autoFocus />
          </div>
          <div className="flex gap-2">
            <button type="submit" className={btnPrimary}>Simpan</button>
            <button type="button" onClick={() => setAddOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold text-stone-500">Batal</button>
          </div>
        </form>
      )}

      {bulkOpen && (
        <form onSubmit={bulkAdd} className="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4">
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Dari meja</label>
            <input type="number" min={1} value={bulkFrom} onChange={(e) => setBulkFrom(e.target.value)} className={`${inputCls} w-24`} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Ke meja</label>
            <input type="number" min={1} value={bulkTo} onChange={(e) => setBulkTo(e.target.value)} className={`${inputCls} w-24`} />
          </div>
          <div className="flex gap-2">
            <button type="submit" className={btnPrimary}>Cipta</button>
            <button type="button" onClick={() => setBulkOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold text-stone-500">Batal</button>
          </div>
        </form>
      )}

      {tables.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-12 text-center text-sm text-stone-400">
          Tiada meja lagi. Cipta satu atau guna <b>+ Banyak</b> untuk siapkan 1–N meja dengan cepat.
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {tables.map((t) => {
            const session = sessionFor(t.id);
            const open = Boolean(session);
            return (
              <div key={t.id} className={`rounded-2xl border bg-white p-4 ${open ? "border-brand-300 ring-2 ring-brand-100" : "border-stone-200"}`}>
                <div className="flex items-center justify-between">
                  <p className="text-2xl font-black text-ink">{t.number}</p>
                  <span className={`rounded-full px-2 py-0.5 text-[10px] font-black uppercase ${open ? "bg-brand-100 text-brand-700" : "bg-emerald-100 text-emerald-700"}`}>
                    {open ? "Buka" : "Kosong"}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-stone-400">{t.capacity ?? 2} tempat duduk</p>
                {open && session && (
                  <p className="mt-2 rounded-lg bg-stone-50 px-2 py-1 text-xs font-bold text-stone-600">
                    Sesi sejak {timeOnly(session.opened_at)}
                  </p>
                )}
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {open && session && (
                    <button onClick={() => openSettleDialog(t.id, session)} className="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-black text-white transition hover:bg-brand-800">
                      Bil &amp; Bayar
                    </button>
                  )}
                  <button onClick={() => regenerate(t)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-brand-300 hover:text-brand-700">
                    QR
                  </button>
                  <button onClick={() => removeTable(t)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-red-300 hover:text-red-600">
                    Buang
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Settle dialog */}
      {settle && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => !settle.busy && setSettle(null)}>
          <div className="max-h-[85vh] w-full max-w-md overflow-auto rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <p className="text-lg font-black text-ink">
              Bil meja {settle.session.table?.number} 🧾
            </p>
            {!settle.bill ? (
              <p className="py-6 text-center text-sm text-stone-400">Memuatkan bil…</p>
            ) : (
              <>
                <div className="mt-3 space-y-2">
                  {billLines.map((o) => (
                    <div key={o.id} className="rounded-xl border border-stone-200 p-3">
                      <div className="flex items-center justify-between">
                        <p className="text-sm font-black text-ink">{o.order_no}</p>
                        <p className="text-sm font-black text-brand-700">{rm(o.total)}</p>
                      </div>
                      {(o.items ?? []).map((i) => (
                        <p key={i.id} className="text-xs text-stone-500">
                          {i.quantity} × {i.name} — {rm(i.line_total)}
                        </p>
                      ))}
                    </div>
                  ))}
                </div>
                <div className="mt-4 flex items-center justify-between border-t border-stone-200 pt-3">
                  <p className="font-black text-ink">JUMLAH</p>
                  <p className="text-xl font-black text-brand-700">{rm(settle.bill.bill_total)}</p>
                </div>

                <p className="mb-2 mt-4 text-xs font-black uppercase tracking-wide text-stone-500">Kaedah bayaran</p>
                <div className="grid grid-cols-2 gap-2">
                  {METHODS.map((m) => (
                    <button
                      key={m.m}
                      onClick={() => setSettle({ ...settle, method: m.m })}
                      className={`rounded-xl border px-3 py-3 text-sm font-black transition ${
                        settle.method === m.m ? "border-brand-600 bg-brand-50 text-brand-800 ring-2 ring-brand-200" : "border-stone-200 text-stone-600 hover:border-brand-300"
                      }`}
                    >
                      {m.label}
                    </button>
                  ))}
                </div>
                <button onClick={confirmSettle} disabled={!settle.method || settle.busy} className={`${btnPrimary} mt-4 w-full py-3 text-base`}>
                  {settle.busy ? "Menyelesaikan…" : "Sahkan Bayaran & Tutup Sesi"}
                </button>
              </>
            )}
          </div>
        </div>
      )}
    </>
  );
}

export default function MejaPage() {
  return (
    <AppShell active="meja">
      <MejaPageContent />
    </AppShell>
  );
}
