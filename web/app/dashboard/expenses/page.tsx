"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import AppShell from "../../../components/dashboard/AppShell";
import { rm } from "../../../components/dashboard/money";
import { api, ExpenseCategoryInfo, ExpenseInfo, ExpenseListResponse } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";
const METHODS = [
  { m: "", label: "Any method" },
  { m: "cash", label: "Cash" },
  { m: "card", label: "Card" },
  { m: "qr", label: "QR / DuitNow" },
  { m: "other", label: "Other" },
];

function ExpensesPageContent() {
  const token = getToken();

  const [cats, setCats] = useState<ExpenseCategoryInfo[]>([]);
  const [rows, setRows] = useState<ExpenseInfo[]>([]);
  const [summary, setSummary] = useState<ExpenseListResponse["summary"] | null>(null);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [catFilter, setCatFilter] = useState("");
  const [loading, setLoading] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  // modal (add/edit)
  const [open, setOpen] = useState(false);
  const [edit, setEdit] = useState<ExpenseInfo | null>(null);
  const [form, setForm] = useState({
    category_id: "",
    description: "",
    amount: "",
    expense_date: "",
    payment_method: "cash",
    note: "",
  });

  // category manager
  const [catOpen, setCatOpen] = useState(false);
  const [newCat, setNewCat] = useState("");

  const today = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  }, []);

  const loadCats = useCallback(async () => {
    if (!token) return;
    try {
      const r = await api.expenseCategories(token);
      setCats(r.categories);
    } catch {
      /* ignore */
    }
  }, [token]);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setErr(null);
    try {
      const r = await api.expensesIndex(token, {
        from: from || undefined,
        to: to || undefined,
        category_id: catFilter || undefined,
        per_page: 100,
      });
      setRows(r.data);
      setSummary(r.summary);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load expenses.");
    } finally {
      setLoading(false);
    }
  }, [token, from, to, catFilter]);

  useEffect(() => {
    // Load categories on mount.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadCats();
  }, [loadCats]);

  useEffect(() => {
    // Load expenses when the filters change.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [load]);

  function flashOk(text: string) {
    setMsg(text);
    setTimeout(() => setMsg(null), 2500);
  }

  function openAdd() {
    setEdit(null);
    setForm({ category_id: "", description: "", amount: "", expense_date: today, payment_method: "cash", note: "" });
    setOpen(true);
  }

  function openEdit(e: ExpenseInfo) {
    setEdit(e);
    setForm({
      category_id: e.category_id ? String(e.category_id) : "",
      description: e.description,
      amount: e.amount,
      expense_date: e.expense_date,
      payment_method: e.payment_method || "cash",
      note: e.note ?? "",
    });
    setOpen(true);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    setErr(null);
    try {
      const amount = Number(form.amount);
      if (!form.description.trim() || !amount || amount <= 0 || !form.expense_date) {
        setErr("Please fill description, a valid amount and the date.");
        return;
      }
      const payload = {
        category_id: form.category_id ? Number(form.category_id) : undefined,
        description: form.description.trim(),
        amount,
        expense_date: form.expense_date,
        payment_method: form.payment_method || undefined,
        note: form.note.trim() || undefined,
      };
      if (edit) {
        await api.updateExpense(token, edit.id, payload);
        flashOk("Expense updated.");
      } else {
        await api.createExpense(token, payload);
        flashOk("Expense recorded.");
      }
      setOpen(false);
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed to save expense.");
    }
  }

  async function remove(expense: ExpenseInfo) {
    if (!token) return;
    if (!window.confirm(`Delete expense "${expense.description}" (${rm(expense.amount)})?`)) return;
    try {
      await api.deleteExpense(token, expense.id);
      flashOk("Expense deleted.");
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    }
  }

  async function addCat(e: React.FormEvent) {
    e.preventDefault();
    if (!token || !newCat.trim()) return;
    try {
      const r = await api.createExpenseCategory(token, { name: newCat.trim() });
      setNewCat("");
      setForm((f) => ({ ...f, category_id: String(r.category.id) }));
      flashOk("Category added.");
      await loadCats();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed to add category.");
    }
  }

  async function deleteCat(c: ExpenseCategoryInfo) {
    if (!token) return;
    if (!window.confirm(`Delete category "${c.name}"? Existing expenses are kept (moved to no category).`)) return;
    try {
      await api.deleteExpenseCategory(token, c.id);
      if (catFilter === String(c.id)) setCatFilter("");
      flashOk("Category deleted.");
      await loadCats();
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    }
  }

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Expenses 🧾💸</h1>
          <p className="text-sm text-stone-500">Money out · Sales − Expenses = Net Position</p>
        </div>
        <button onClick={openAdd} className="rounded-xl bg-rasa-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-rasa-600/25 transition hover:bg-rasa-700">
          + Record expense
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-2 rounded-2xl border border-stone-200 bg-white p-3">
        <div>
          <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">From</label>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={inputCls} />
        </div>
        <div>
          <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">To</label>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={inputCls} />
        </div>
        <div>
          <label className="mb-1 block text-[10px] font-black uppercase tracking-wide text-stone-400">Category</label>
          <div className="flex items-center gap-1.5">
            <select value={catFilter} onChange={(e) => setCatFilter(e.target.value)} className={inputCls}>
              <option value="">All categories</option>
              {cats.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <button onClick={() => setCatOpen(true)} className="shrink-0 rounded-xl border border-stone-200 px-2.5 py-2 text-sm font-black text-stone-500 transition hover:border-rasa-300 hover:text-rasa-600" title="Manage categories">
              +
            </button>
          </div>
        </div>
        <button onClick={() => void load()} disabled={loading} className="rounded-xl bg-stone-800 px-4 py-2 text-sm font-black text-white transition hover:bg-stone-700 disabled:opacity-50">
          {loading ? "…" : "Refresh"}
        </button>
        <div className="ml-auto rounded-xl bg-rasa-50 px-4 py-2 text-right">
          <p className="text-[10px] font-black uppercase text-rasa-400">Total spent</p>
          <p className="text-lg font-black leading-tight text-rasa-600">{rm(summary?.total_amount ?? 0)}</p>
        </div>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {/* By category */}
      {summary && summary.by_category.length > 0 && (
        <div className="rounded-2xl border border-stone-200 bg-white p-5">
          <p className="mb-3 text-xs font-black uppercase tracking-wide text-stone-400">By category</p>
          <div className="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
            {summary.by_category.map((c) => {
              const max = Math.max(...summary.by_category.map((x) => Number(x.amount)), 0.01);
              return (
                <div key={c.category} className="rounded-xl border border-stone-200 px-3 py-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-bold text-ink">{c.category}</span>
                    <span className="font-black text-rasa-600">{rm(c.amount)}</span>
                  </div>
                  <div className="mt-1.5 h-1.5 rounded-full bg-stone-100">
                    <div className="h-1.5 rounded-full bg-rasa-500" style={{ width: `${(Number(c.amount) / max) * 100}%` }} />
                  </div>
                  <p className="mt-1 text-[11px] text-stone-400">{c.count} expense{c.count === 1 ? "" : "s"}</p>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* List */}
      <div className="rounded-2xl border border-stone-200 bg-white">
        {rows.length === 0 ? (
          <p className="p-8 text-center text-sm text-stone-400">No expenses in this range. Record your first expense.</p>
        ) : (
          <div className="divide-y divide-stone-100">
            {rows.map((e) => (
              <div key={e.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-black text-ink">{e.description}</p>
                    {e.category && <span className="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-black text-stone-500">{e.category.name}</span>}
                    {e.payment_method && <span className="rounded-full bg-rasa-50 px-2 py-0.5 text-[10px] font-black text-rasa-600">{e.payment_method}</span>}
                  </div>
                  <p className="text-xs text-stone-400">
                    {e.expense_date}
                    {e.created_by ? ` · by ${e.created_by.name}` : ""}
                  </p>
                </div>
                <p className="text-base font-black text-ink">{rm(e.amount)}</p>
                <div className="flex gap-1.5">
                  <button onClick={() => openEdit(e)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-rasa-300 hover:text-rasa-600">
                    Edit
                  </button>
                  <button onClick={() => remove(e)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold text-stone-600 hover:border-red-300 hover:text-red-600">
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Record / edit dialog */}
      {open && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => setOpen(false)}>
          <div className="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <p className="text-lg font-black text-ink">{edit ? "Edit expense" : "Record expense"}</p>
            <form onSubmit={submit} className="mt-4 space-y-3">
              <div>
                <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Description</label>
                <input required value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className={inputCls} placeholder="e.g. Chicken 10kg, Electricity bill" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Amount (RM)</label>
                  <input type="number" min="0.01" step="0.01" required value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} className={inputCls} placeholder="0.00" />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Date</label>
                  <input type="date" required value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} className={inputCls} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Category</label>
                  <select value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })} className={inputCls}>
                    <option value="">— No category —</option>
                    {cats.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Paid by</label>
                  <select value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} className={inputCls}>
                    {METHODS.filter((m) => m.m).map((m) => (
                      <option key={m.m} value={m.m}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Note (optional)</label>
                <input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} className={inputCls} placeholder="Extra details" />
              </div>
              <div className="flex gap-2 pt-1">
                <button type="submit" className="flex-1 rounded-xl bg-rasa-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-rasa-700">
                  {edit ? "Save changes" : "Record expense"}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-bold text-stone-600">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Category manager */}
      {catOpen && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => setCatOpen(false)}>
          <div className="w-full max-w-sm rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <p className="text-lg font-black text-ink">Expense categories</p>
            <p className="text-xs text-stone-500">Group your money-out, e.g. Ingredients, Staff, Rent, Utilities.</p>
            <form onSubmit={addCat} className="mt-3 flex gap-2">
              <input value={newCat} onChange={(e) => setNewCat(e.target.value)} className={inputCls} placeholder="New category name" />
              <button type="submit" className="shrink-0 rounded-xl bg-rasa-600 px-4 py-2 text-sm font-black text-white transition hover:bg-rasa-700">
                Add
              </button>
            </form>
            <div className="mt-4 space-y-2">
              {cats.length === 0 && <p className="text-center text-sm text-stone-400">No categories yet.</p>}
              {cats.map((c) => (
                <div key={c.id} className="flex items-center justify-between gap-2 rounded-xl border border-stone-200 px-3 py-2">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-ink">{c.name}</p>
                    {c.description && <p className="truncate text-xs text-stone-400">{c.description}</p>}
                  </div>
                  <button onClick={() => deleteCat(c)} className="shrink-0 rounded-lg border border-stone-200 px-2.5 py-1 text-xs font-bold text-stone-500 hover:border-red-300 hover:text-red-600">
                    Delete
                  </button>
                </div>
              ))}
            </div>
            <button onClick={() => setCatOpen(false)} className="mt-4 w-full rounded-xl border border-stone-200 py-2 text-sm font-black text-stone-600 hover:border-stone-300">
              Close
            </button>
          </div>
        </div>
      )}
    </>
  );
}

export default function ExpensesPage() {
  return (
    <AppShell active="expenses">
      <ExpensesPageContent />
    </AppShell>
  );
}
