"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { api, StaffInfo } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";

function StaffContent() {
  const { role } = useDashboard();
  const token = getToken();

  const [staff, setStaff] = useState<StaffInfo[]>([]);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", email: "", role: "staff" as "staff" | "manager", position: "", phone: "" });

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const r = await api.staffIndex(token);
      setStaff(r.staff);
      setErr(null);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load staff.");
    }
  }, [token]);

  useEffect(() => {
    if (role === "owner" || role === "manager") {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [load, role]);

  function flashOk(text: string) {
    setMsg(text);
    setTimeout(() => setMsg(null), 3000);
  }

  async function addStaff(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    setBusy(true);
    setErr(null);
    try {
      await api.createStaff(token, {
        name: form.name,
        email: form.email,
        role: form.role,
        position: form.position || undefined,
        phone: form.phone || undefined,
      });
      setOpen(false);
      setForm({ name: "", email: "", role: "staff", position: "", phone: "" });
      flashOk("Staff added. A welcome email with a temporary password was sent.");
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed to add staff.");
    } finally {
      setBusy(false);
    }
  }

  async function toggleActive(s: StaffInfo) {
    if (!token) return;
    try {
      await api.updateStaff(token, s.id, { is_active: !s.is_active });
      flashOk(`${s.name} ${s.is_active ? "deactivated" : "activated"}.`);
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    }
  }

  const activeCount = staff.filter((s) => s.is_active).length;

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Staff 👥</h1>
          <p className="text-sm text-stone-500">
            {staff.length} staff · {activeCount} active
          </p>
        </div>
        <button
          onClick={() => setOpen(true)}
          className="rounded-xl bg-rasa-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-rasa-600/25 transition hover:bg-rasa-700"
        >
          + Add staff
        </button>
      </div>

      <div className="rounded-2xl border border-brand-100 bg-white p-5">
        <p className="text-sm font-bold text-ink">How staff take orders</p>
        <p className="mt-1 text-sm text-stone-500">
          Each staff member logs in to app.sajio.my, presses <b>Clock in</b> when their shift starts, then takes orders from the
          POS. Only on-duty staff can create orders. Managers can too. You (the owner) are always allowed.
        </p>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {staff.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-12 text-center text-sm text-stone-400">
          No staff yet. Click <b>+ Add staff</b> to invite your first team member.
        </div>
      ) : (
        <div className="space-y-2.5">
          {staff.map((s) => (
            <div key={s.id} className={`flex flex-wrap items-center gap-3 rounded-2xl border bg-white p-4 ${s.is_active ? "border-stone-200" : "border-stone-200 opacity-60"}`}>
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-rasa-50 text-base font-black text-rasa-600 ring-1 ring-rasa-100">
                {s.name
                  .split(" ")
                  .slice(0, 2)
                  .map((p) => p[0])
                  .join("")
                  .toUpperCase()}
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-black text-ink">{s.name}</p>
                  {s.staff_code && <span className="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-black text-stone-500">{s.staff_code}</span>}
                  <span className={`rounded-full px-2 py-0.5 text-[10px] font-black ${s.role === "manager" ? "bg-ink text-white" : "bg-rasa-50 text-rasa-600 ring-1 ring-rasa-200"}`}>
                    {s.role_label}
                  </span>
                  {!s.is_active && <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-black text-red-600">Inactive</span>}
                </div>
                <p className="truncate text-sm text-stone-500">
                  {s.email}
                  {s.position ? ` · ${s.position}` : ""}
                </p>
              </div>
              <button
                onClick={() => toggleActive(s)}
                className={`rounded-xl px-4 py-2 text-xs font-black transition ${
                  s.is_active ? "border border-stone-300 text-stone-600 hover:border-red-300 hover:text-red-600" : "bg-emerald-600 text-white hover:bg-emerald-500"
                }`}
              >
                {s.is_active ? "Deactivate" : "Activate"}
              </button>
            </div>
          ))}
        </div>
      )}

      {open && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => setOpen(false)}>
          <div className="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <p className="text-lg font-black text-ink">Add staff</p>
            <p className="text-xs text-stone-500">A welcome email with a temporary password will be sent.</p>
            <form onSubmit={addStaff} className="mt-4 space-y-3">
              <div>
                <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Full name</label>
                <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className={inputCls} placeholder="Ahmad bin Ali" />
              </div>
              <div>
                <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Email</label>
                <input type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className={inputCls} placeholder="ahmad@yourkopitiam.my" />
              </div>
              <div>
                <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Role</label>
                <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as "staff" | "manager" })} className={inputCls}>
                  <option value="staff">Staff</option>
                  <option value="manager">Manager</option>
                </select>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Position</label>
                  <input value={form.position} onChange={(e) => setForm({ ...form, position: e.target.value })} className={inputCls} placeholder="Waiter" />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Phone</label>
                  <input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className={inputCls} placeholder="012-3456789" />
                </div>
              </div>
              <div className="flex gap-2 pt-1">
                <button type="submit" disabled={busy} className="flex-1 rounded-xl bg-rasa-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-rasa-700 disabled:opacity-50">
                  {busy ? "Adding…" : "Add staff"}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-bold text-stone-600">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

export default function StaffPage() {
  return (
    <AppShell active="staff">
      <StaffContent />
    </AppShell>
  );
}
