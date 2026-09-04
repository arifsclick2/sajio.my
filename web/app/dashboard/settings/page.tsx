"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { api, ProfileBranding, ProfileSettings, restaurantRootUrl } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const inputCls =
  "w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">{label}</label>
      {children}
    </div>
  );
}

function SettingsPageContent() {
  const { restaurant } = useDashboard();
  const token = getToken();

  const [name, setName] = useState("");
  const [settings, setSettings] = useState<ProfileSettings>({});
  const [branding, setBranding] = useState<ProfileBranding>({});
  const [profileSub, setProfileSub] = useState<string>("");
  const [loaded, setLoaded] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [busyBiz, setBusyBiz] = useState(false);
  const [busyBrand, setBusyBrand] = useState(false);
  const [copied, setCopied] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const p = await api.profile(token);
      setName(p.restaurant.name ?? "");
      setProfileSub(p.restaurant.subdomain ?? "");
      setSettings(p.settings);
      setBranding(p.branding);
      setErr(null);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load settings.");
    } finally {
      setLoaded(true);
    }
  }, [token]);

  useEffect(() => {
    // Load profile on mount.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void load();
  }, [load]);

  function flashOk(text: string) {
    setMsg(text);
    setTimeout(() => setMsg(null), 2500);
  }

  function setS(key: keyof ProfileSettings, value: string) {
    setSettings((s) => ({ ...s, [key]: value || null }));
  }

  // Subdomain comes from the live profile (authoritative); the session copy is a fallback.
  const subdomain = profileSub || (restaurant?.subdomain ?? "");
  const rootUrl = subdomain ? restaurantRootUrl(subdomain) : "";
  const color = /^#[0-9a-fA-F]{6}$/.test(branding.brand_color ?? "") ? (branding.brand_color ?? "#e82d4b") : "#e82d4b";

  async function saveBusiness(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    setBusyBiz(true);
    setErr(null);
    try {
      await api.updateProfileSettings(token, {
        name: name.trim() || undefined,
        phone: settings.phone || null,
        email: settings.email || null,
        address: settings.address || null,
        city: settings.city || null,
        state: settings.state || null,
        postcode: settings.postcode || null,
      });
      flashOk("Business details saved.");
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed to save.");
    } finally {
      setBusyBiz(false);
    }
  }

  async function saveBranding(e: React.FormEvent) {
    e.preventDefault();
    if (!token) return;
    setBusyBrand(true);
    setErr(null);
    try {
      if (branding.brand_color && !/^#[0-9a-fA-F]{6}$/.test(branding.brand_color)) {
        setErr("Brand color must be a hex color like #E82D4B.");
        return;
      }
      await api.updateProfileBranding(token, {
        logo_url: branding.logo_url || undefined,
        brand_color: branding.brand_color || undefined,
        receipt_header: branding.receipt_header || undefined,
        receipt_footer: branding.receipt_footer || undefined,
      });
      flashOk("Branding saved.");
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Failed to save.");
    } finally {
      setBusyBrand(false);
    }
  }

  if (!loaded) {
    return (
      <div className="grid place-items-center py-24 text-sm font-semibold text-stone-400">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-rasa-200 border-t-rasa-500" />
      </div>
    );
  }

  return (
    <>
      <div>
        <h1 className="text-2xl font-black tracking-tight text-ink">Settings ⚙️</h1>
        <p className="text-sm text-stone-500">Your Sajio link, business details & branding</p>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      <div className="grid gap-4 lg:grid-cols-2">
        {/* ---- Your Sajio link ---- */}
        <div className="rounded-2xl border border-stone-200 bg-white p-6">
          <p className="font-black text-ink">Your Sajio link 🔗</p>
          <p className="text-sm text-stone-500">Customers use this address to see your menu & order.</p>

          {rootUrl ? (
            <>
              <a href={rootUrl} target="_blank" rel="noreferrer" className="mt-4 block break-all rounded-xl border-2 border-rasa-100 bg-rasa-50/60 px-4 py-3 text-lg font-black text-rasa-600 transition hover:border-rasa-300">
                {rootUrl}
              </a>
              <p className="mt-2 text-xs text-stone-400">
                Your table QR cards open <span className="font-bold text-stone-500">{rootUrl}/order/TABLE-TOKEN</span> — each table has its
                own link on the <a className="font-black text-rasa-600 hover:underline" href="/dashboard/tables">Tables</a> page.
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                <button
                  onClick={async () => {
                    try {
                      await navigator.clipboard.writeText(rootUrl);
                      setCopied(true);
                      setTimeout(() => setCopied(false), 2000);
                    } catch {
                      /* ignore */
                    }
                  }}
                  className="rounded-xl bg-rasa-600 px-4 py-2 text-sm font-black text-white transition hover:bg-rasa-700"
                >
                  {copied ? "✓ Copied!" : "Copy link"}
                </button>
                <a href="/dashboard/tables" className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold text-stone-600 transition hover:border-rasa-300 hover:text-rasa-600">
                  View table QR cards
                </a>
              </div>
            </>
          ) : (
            <p className="mt-3 text-sm text-stone-400">No subdomain assigned yet.</p>
          )}
        </div>

        {/* ---- Business profile ---- */}
        <form onSubmit={saveBusiness} className="rounded-2xl border border-stone-200 bg-white p-6">
          <p className="font-black text-ink">Business details 🏪</p>
          <p className="text-sm text-stone-500">Shown on receipts and your menu.</p>
          <div className="mt-4 space-y-3">
            <Field label="Business name">
              <input value={name} onChange={(e) => setName(e.target.value)} className={inputCls} placeholder="Your restaurant name" />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Phone">
                <input value={settings.phone ?? ""} onChange={(e) => setS("phone", e.target.value)} className={inputCls} placeholder="012-3456789" />
              </Field>
              <Field label="Email">
                <input type="email" value={settings.email ?? ""} onChange={(e) => setS("email", e.target.value)} className={inputCls} placeholder="hello@yourkopitiam.my" />
              </Field>
            </div>
            <Field label="Address">
              <input value={settings.address ?? ""} onChange={(e) => setS("address", e.target.value)} className={inputCls} placeholder="12, Jalan Tunku Abdul Rahman" />
            </Field>
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="City">
                <input value={settings.city ?? ""} onChange={(e) => setS("city", e.target.value)} className={inputCls} placeholder="Kuala Lumpur" />
              </Field>
              <Field label="State">
                <input value={settings.state ?? ""} onChange={(e) => setS("state", e.target.value)} className={inputCls} placeholder="Wilayah Persekutuan" />
              </Field>
              <Field label="Postcode">
                <input value={settings.postcode ?? ""} onChange={(e) => setS("postcode", e.target.value)} className={inputCls} placeholder="50000" />
              </Field>
            </div>
          </div>
          <button type="submit" disabled={busyBiz} className="mt-4 rounded-xl bg-rasa-600 px-5 py-2.5 text-sm font-black text-white transition hover:bg-rasa-700 disabled:opacity-50">
            {busyBiz ? "Saving…" : "Save business details"}
          </button>
        </form>

        {/* ---- Branding ---- */}
        <form onSubmit={saveBranding} className="rounded-2xl border border-stone-200 bg-white p-6 lg:col-span-2">
          <p className="font-black text-ink">Branding 🎨</p>
          <p className="text-sm text-stone-500">Your logo & colour appear on the customer menu, QR pages and receipts.</p>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="sm:col-span-2 lg:col-span-1">
              <Field label="Logo URL">
                <input value={branding.logo_url ?? ""} onChange={(e) => setBranding({ ...branding, logo_url: e.target.value })} className={inputCls} placeholder="https://…/logo.png" />
              </Field>
              <div className="mt-2 flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl border border-stone-200 bg-stone-50">
                {branding.logo_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={branding.logo_url} alt="logo preview" className="h-full w-full object-contain" onError={(e) => ((e.target as HTMLImageElement).style.display = "none")} />
                ) : (
                  <span className="text-xs font-bold text-stone-300">No logo</span>
                )}
              </div>
            </div>
            <div>
              <Field label="Brand colour">
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={color}
                    onChange={(e) => setBranding({ ...branding, brand_color: e.target.value })}
                    className="h-10 w-12 cursor-pointer rounded-lg border border-stone-300 bg-white p-1"
                  />
                  <input value={branding.brand_color ?? ""} onChange={(e) => setBranding({ ...branding, brand_color: e.target.value })} className={inputCls} placeholder="#E82D4B" />
                </div>
              </Field>
              <p className="mt-1 text-[11px] text-stone-400">Used across the customer menu & receipts.</p>
            </div>
            <div>
              <Field label="Receipt header">
                <input value={branding.receipt_header ?? ""} onChange={(e) => setBranding({ ...branding, receipt_header: e.target.value })} className={inputCls} placeholder="Thank you for dining with us!" />
              </Field>
            </div>
            <div>
              <Field label="Receipt footer">
                <input value={branding.receipt_footer ?? ""} onChange={(e) => setBranding({ ...branding, receipt_footer: e.target.value })} className={inputCls} placeholder="Follow us on Instagram…" />
              </Field>
            </div>
          </div>
          <button type="submit" disabled={busyBrand} className="mt-4 rounded-xl bg-rasa-600 px-5 py-2.5 text-sm font-black text-white transition hover:bg-rasa-700 disabled:opacity-50">
            {busyBrand ? "Saving…" : "Save branding"}
          </button>
        </form>
      </div>
    </>
  );
}

export default function SettingsPage() {
  return (
    <AppShell active="settings">
      <SettingsPageContent />
    </AppShell>
  );
}
