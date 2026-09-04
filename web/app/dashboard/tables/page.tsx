"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { rm, timeOnly } from "../../../components/dashboard/money";
import { printReceipt } from "../../../components/dashboard/printReceipt";
import { api, BillResponse, restaurantOrderUrl, SessionInfo, TableInfo } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";
const btnPrimary = "rounded-xl bg-rasa-600 px-4 py-2 text-sm font-black text-white transition hover:bg-rasa-700 disabled:opacity-50";
const METHODS = [
  { m: "cash", label: "💵 Cash" },
  { m: "card", label: "💳 Card" },
  { m: "qr", label: "📱 QR / DuitNow" },
  { m: "other", label: "🔘 Other" },
];

function TablesPageContent() {
  const { role, restaurant } = useDashboard();
  const token = getToken();

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

  // QR / customer ordering dialog
  const [qrTable, setQrTable] = useState<TableInfo | null>(null);
  const [copied, setCopied] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [t, s] = await Promise.all([api.tables(token), api.openSessions(token)]);
      setTables(t.tables);
      setSessions(s.sessions);
      setErr(null);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load tables.");
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
      flashOk("Table added.");
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed.");
    }
  }

  async function bulkAdd(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    const from = Number(bulkFrom);
    const to = Number(bulkTo);
    if (!from || !to || to < from) {
      setErr("Invalid range.");
      return;
    }
    try {
      const r = await api.bulkTables(token, { from, to });
      setBulkOpen(false);
      flashOk(`${r.count} tables added.`);
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed.");
    }
  }

  async function removeTable(t: TableInfo) {
    if (!token) return;
    if (!window.confirm(`Delete table ${t.number}?`)) return;
    try {
      await api.deleteTable(token, t.id);
      flashOk("Table deleted.");
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    }
  }

  async function regenerate(t: TableInfo) {
    if (!token) return;
    if (!window.confirm(`Change QR token for table ${t.number}? The old QR card will no longer work.`)) return;
    try {
      const r = await api.regenerateTableToken(token, t.id);
      setErr(null);
      flashOk(`New token for table ${r.table.number}: ${r.table.public_token}`);
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    }
  }

  async function openSettleDialog(tableId: number, session: SessionInfo) {
    if (!token) return;
    setSettle({ session, bill: null, method: "", busy: false });
    try {
      const bill = await api.tableCurrent(token, tableId);
      setSettle((s) => (s ? { ...s, bill } : s));
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load bill.");
    }
  }

  async function confirmSettle() {
    const s = settle;
    if (!token || !s || !s.method) return;
    setSettle({ ...s, busy: true });
    try {
      await api.settleSession(token, s.session.id, { method: s.method });
      setSettle(null);
      flashOk("Bill settled. Table is now free.");
      void load();
      // Print session receipt
      try {
        const receipt = await api.sessionReceipt(token, s.session.id);
        printReceipt(receipt.receipt);
      } catch {
        /* printing is best-effort */
      }
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to settle the bill.");
      setSettle((prev) => (prev ? { ...prev, busy: false } : prev));
    }
  }

  const billLines = settle?.bill?.orders ?? [];

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Tables 🪑</h1>
          <p className="text-sm text-stone-500">
            {tables.length} tables · {sessions.length} open sessions
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => setBulkOpen(true)} className="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-black text-stone-700 transition hover:border-rasa-300 hover:text-rasa-600">
            + Bulk (1–N)
          </button>
          <button onClick={() => setAddOpen(true)} className={btnPrimary}>+ Table</button>
        </div>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {addOpen && (
        <form onSubmit={addTable} className="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4">
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Table number / name</label>
            <input required value={newNum} onChange={(e) => setNewNum(e.target.value)} className={inputCls} placeholder="e.g. 5 or Patio" autoFocus />
          </div>
          <div className="flex gap-2">
            <button type="submit" className={btnPrimary}>Save</button>
            <button type="button" onClick={() => setAddOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold text-stone-500">Cancel</button>
          </div>
        </form>
      )}

      {bulkOpen && (
        <form onSubmit={bulkAdd} className="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4">
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">From table</label>
            <input type="number" min={1} value={bulkFrom} onChange={(e) => setBulkFrom(e.target.value)} className={`${inputCls} w-24`} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">To table</label>
            <input type="number" min={1} value={bulkTo} onChange={(e) => setBulkTo(e.target.value)} className={`${inputCls} w-24`} />
          </div>
          <div className="flex gap-2">
            <button type="submit" className={btnPrimary}>Create</button>
            <button type="button" onClick={() => setBulkOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold text-stone-500">Cancel</button>
          </div>
        </form>
      )}

      {tables.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-12 text-center text-sm text-stone-400">
          No tables yet. Create one, or use <b>+ Bulk</b> to set up tables 1–N in seconds.
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {tables.map((t) => {
            const session = sessionFor(t.id);
            const open = Boolean(session);
            return (
              <div key={t.id} className={`rounded-2xl border bg-white p-4 ${open ? "border-rasa-300 ring-2 ring-rasa-100" : "border-stone-200"}`}>
                <div className="flex items-center justify-between">
                  <p className="text-2xl font-black text-ink">{t.number}</p>
                  <span className={`rounded-full px-2 py-0.5 text-[10px] font-black uppercase ${open ? "bg-rasa-100 text-rasa-600" : "bg-emerald-100 text-emerald-700"}`}>
                    {open ? "Open" : "Free"}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-stone-400">{t.capacity ?? 2} seats</p>
                {open && session && (
                  <p className="mt-2 rounded-lg bg-stone-50 px-2 py-1 text-xs font-bold text-stone-600">
                    Session since {timeOnly(session.opened_at)}
                  </p>
                )}
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {open && session && (
                    <button onClick={() => openSettleDialog(t.id, session)} className="rounded-lg bg-rasa-600 px-3 py-1.5 text-xs font-black text-white transition hover:bg-rasa-700">
                      Bill & Pay
                    </button>
                  )}
                  <button onClick={() => setQrTable(t)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-rasa-300 hover:text-rasa-600">
                    QR 📱
                  </button>
                  <button onClick={() => removeTable(t)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-red-300 hover:text-red-600">
                    Delete
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
              Table bill {settle.session.table?.number} 🧾
            </p>
            {!settle.bill ? (
              <p className="py-6 text-center text-sm text-stone-400">Loading bill…</p>
            ) : (
              <>
                <div className="mt-3 space-y-2">
                  {billLines.map((o) => (
                    <div key={o.id} className="rounded-xl border border-stone-200 p-3">
                      <div className="flex items-center justify-between">
                        <p className="text-sm font-black text-ink">{o.order_no}</p>
                        <p className="text-sm font-black text-rasa-600">{rm(o.total)}</p>
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
                  <p className="font-black text-ink">TOTAL</p>
                  <p className="text-xl font-black text-rasa-600">{rm(settle.bill.bill_total)}</p>
                </div>

                <p className="mb-2 mt-4 text-xs font-black uppercase tracking-wide text-stone-500">Payment method</p>
                <div className="grid grid-cols-2 gap-2">
                  {METHODS.map((m) => (
                    <button
                      key={m.m}
                      onClick={() => setSettle({ ...settle, method: m.m })}
                      className={`rounded-xl border px-3 py-3 text-sm font-black transition ${
                        settle.method === m.m ? "border-rasa-500 bg-rasa-50 text-rasa-700 ring-2 ring-rasa-200" : "border-stone-200 text-stone-600 hover:border-rasa-300"
                      }`}
                    >
                      {m.label}
                    </button>
                  ))}
                </div>
                <button onClick={confirmSettle} disabled={!settle.method || settle.busy} className={`${btnPrimary} mt-4 w-full py-3 text-base`}>
                  {settle.busy ? "Processing…" : "Confirm Payment & Close Session"}
                </button>
              </>
            )}
          </div>
        </div>
      )}

      {/* QR / customer ordering dialog */}
      {qrTable && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => setQrTable(null)}>
          <div
            className="w-full max-w-sm rounded-2xl border border-stone-200 bg-white p-6 text-center shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <p className="text-lg font-black text-ink">Table {qrTable.number} · QR ordering 📱</p>
            <p className="mt-1 text-xs text-stone-500">Customers scan to open the menu and order from their phone.</p>

            {(() => {
              const qrToken = qrTable.public_token;
              if (!qrToken) {
                return <p className="mt-4 text-sm font-semibold text-stone-500">No QR token on this table yet.</p>;
              }
              const url = restaurantOrderUrl(restaurant?.subdomain, qrToken);
              return (
                <>
                  <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    className="mx-auto mt-4 block w-fit rounded-2xl border border-stone-200 bg-white p-2 shadow-sm transition hover:border-rasa-300"
                  >
                    {/* QR via public image service — no QR library installed */}
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={`https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(url)}`}
                      alt={`QR code for table ${qrTable.number}`}
                      width={220}
                      height={220}
                      className="h-44 w-44 rounded-xl"
                    />
                  </a>
                  <button
                    onClick={async () => {
                      try {
                        await navigator.clipboard.writeText(url);
                        setCopied(true);
                        setTimeout(() => setCopied(false), 2000);
                      } catch {
                        /* clipboard may be blocked; the link is shown below */
                      }
                    }}
                    className="mt-3 rounded-xl border border-rasa-200 bg-rasa-50 px-4 py-2 text-sm font-black text-rasa-600 transition hover:bg-rasa-100"
                  >
                    {copied ? "✓ Copied!" : "Copy order link"}
                  </button>
                  <p className="mt-2 break-all rounded-lg bg-stone-50 px-3 py-2 text-[11px] font-semibold text-stone-500">{url}</p>
                  <button
                    onClick={() => {
                      void regenerate(qrTable);
                      setQrTable(null);
                    }}
                    className="mt-3 text-xs font-bold text-stone-400 underline-offset-2 hover:text-rasa-600 hover:underline"
                  >
                    Regenerate token (old QR card stops working)
                  </button>
                </>
              );
            })()}

            <button
              onClick={() => setQrTable(null)}
              className="mt-4 w-full rounded-xl border border-stone-200 py-2 text-sm font-black text-stone-600 hover:border-stone-300"
            >
              Close
            </button>
          </div>
        </div>
      )}
    </>
  );
}

export default function TablesPage() {
  return (
    <AppShell active="tables">
      <TablesPageContent />
    </AppShell>
  );
}
