"use client";

import { useCallback, useEffect, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { rm } from "../../../components/dashboard/money";
import { api, CategoryInfo, ProductInfo } from "../../../lib/api";
import { getToken } from "../../../lib/session";

interface ModalProps {
  title: string;
  children: React.ReactNode;
  onClose: () => void;
}

function Modal({ title, children, onClose }: ModalProps) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
        <p className="text-lg font-black text-ink">{title}</p>
        <div className="mt-4 space-y-4">{children}</div>
      </div>
    </div>
  );
}

const inputCls =
  "w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100";
const btnPrimary = "rounded-xl bg-brand-700 px-4 py-2 text-sm font-black text-white transition hover:bg-brand-800 disabled:opacity-50";

function MenuPageContent() {
  const { role } = useDashboard();
  const token = getToken();

  const [categories, setCategories] = useState<CategoryInfo[]>([]);
  const [products, setProducts] = useState<ProductInfo[]>([]);
  const [activeCat, setActiveCat] = useState<number | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  // modals
  const [catModal, setCatModal] = useState<null | { id?: number; name: string; description: string }>(null);
  const [prodModal, setProdModal] = useState<null | {
    id?: number;
    category_id: number;
    name: string;
    price: string;
    description: string;
    available: boolean;
    is_active: boolean;
  }>(null);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [c, p] = await Promise.all([api.categories(token), api.products(token, { per_page: 200 })]);
      setCategories(c.categories);
      const list = (p as unknown as { data: ProductInfo[] }).data ?? [];
      setProducts(list);
      setErr(null);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal memuat menu.");
    }
  }, [token]);

  useEffect(() => {
    if (role === "owner" || role === "manager") {
      // Initial menu fetch on mount — the rule flags data loads in effects.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [load, role]);

  const shown = activeCat ? products.filter((p) => p.category_id === activeCat) : products;

  function flashOk(text: string) {
    setMsg(text);
    setTimeout(() => setMsg(null), 2500);
  }

  /* ---------------- categories ---------------- */

  async function saveCategory(e: React.FormEvent) {
    e.preventDefault();
    if (!token || !catModal) return;
    try {
      if (catModal.id) {
        await api.updateCategory(token, catModal.id, { name: catModal.name, description: catModal.description || undefined });
      } else {
        await api.createCategory(token, { name: catModal.name, description: catModal.description || undefined });
      }
      setCatModal(null);
      flashOk("Kategori disimpan.");
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Gagal menyimpan kategori.");
    }
  }

  async function removeCategory(c: CategoryInfo) {
    if (!token) return;
    if (!window.confirm(`Buang kategori "${c.name}"?`)) return;
    try {
      await api.deleteCategory(token, c.id);
      flashOk("Kategori dibuang.");
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal.");
    }
  }

  /* ---------------- products ---------------- */

  function openNewProduct() {
    const first = categories[0];
    setProdModal({
      category_id: first?.id ?? 0,
      name: "",
      price: "",
      description: "",
      available: true,
      is_active: true,
    });
  }

  async function saveProduct(e: React.FormEvent) {
    e.preventDefault();
    if (!token || !prodModal) return;
    const price = Number(prodModal.price);
    if (Number.isNaN(price) || price < 0 || !prodModal.category_id) {
      setErr("Sila pilih kategori dan harga yang sah.");
      return;
    }
    try {
      const data = {
        category_id: prodModal.category_id,
        name: prodModal.name,
        price,
        description: prodModal.description || undefined,
        available: prodModal.available,
        is_active: prodModal.is_active,
      };
      if (prodModal.id) await api.updateProduct(token, prodModal.id, data);
      else await api.createProduct(token, data);
      setProdModal(null);
      flashOk("Produk disimpan.");
      void load();
    } catch (e2) {
      setErr(e2 instanceof Error ? e2.message : "Gagal menyimpan produk.");
    }
  }

  async function toggleProduct(p: ProductInfo) {
    if (!token) return;
    try {
      await api.updateProduct(token, p.id, { available: !p.available });
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal.");
    }
  }

  async function removeProduct(p: ProductInfo) {
    if (!token) return;
    if (!window.confirm(`Buang "${p.name}"?`)) return;
    try {
      await api.deleteProduct(token, p.id);
      flashOk("Produk dibuang.");
      void load();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Gagal.");
    }
  }

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">Menu 🍽️</h1>
          <p className="text-sm text-stone-500">Kategori &amp; produk untuk POS dan QR ordering.</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => setCatModal({ name: "", description: "" })} className="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-black text-stone-700 transition hover:border-brand-300 hover:text-brand-700">
            + Kategori
          </button>
          <button onClick={openNewProduct} disabled={categories.length === 0} className={btnPrimary}>
            + Produk
          </button>
        </div>
      </div>

      {msg && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{msg}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
        {/* Categories */}
        <aside className="h-fit space-y-1 rounded-2xl border border-stone-200 bg-white p-3">
          <button
            onClick={() => setActiveCat(null)}
            className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm font-bold ${activeCat === null ? "bg-brand-700 text-white" : "text-stone-600 hover:bg-brand-50"}`}
          >
            <span>Semua</span>
            <span className="text-xs opacity-70">{products.length}</span>
          </button>
          {categories.map((c) => (
            <div key={c.id} className="group flex items-center gap-1">
              <button
                onClick={() => setActiveCat(c.id)}
                className={`flex flex-1 items-center justify-between rounded-xl px-3 py-2 text-left text-sm font-bold ${
                  activeCat === c.id ? "bg-brand-700 text-white" : "text-stone-600 hover:bg-brand-50"
                }`}
              >
                <span className="truncate">{c.name}</span>
                <span className="ml-2 text-xs opacity-70">{c.products_count ?? 0}</span>
              </button>
              <button
                onClick={() => setCatModal({ id: c.id, name: c.name, description: c.description ?? "" })}
                className="rounded-lg p-1 text-xs text-stone-400 opacity-0 transition group-hover:opacity-100 hover:text-brand-700"
                title="Edit"
              >
                ✏️
              </button>
              <button
                onClick={() => removeCategory(c)}
                className="rounded-lg p-1 text-xs text-stone-400 opacity-0 transition group-hover:opacity-100 hover:text-red-600"
                title="Buang"
              >
                🗑️
              </button>
            </div>
          ))}
          {categories.length === 0 && <p className="px-3 py-4 text-center text-xs text-stone-400">Tiada kategori. Tambah satu dahulu.</p>}
        </aside>

        {/* Products */}
        <div className="space-y-3">
          {shown.length === 0 && (
            <div className="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-10 text-center text-sm text-stone-400">
              Tiada produk di sini. Klik <b>+ Produk</b> untuk tambah.
            </div>
          )}
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {shown.map((p) => (
              <div key={p.id} className={`rounded-2xl border bg-white p-4 ${p.available ? "border-stone-200" : "border-stone-200 opacity-60"}`}>
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="truncate font-black text-ink">{p.name}</p>
                    <p className="text-sm text-stone-500">{p.category?.name ?? ""}</p>
                  </div>
                  <p className="shrink-0 font-black text-brand-700">{rm(p.price)}</p>
                </div>
                {p.description && <p className="mt-1 line-clamp-2 text-xs text-stone-400">{p.description}</p>}
                <div className="mt-3 flex items-center justify-between gap-2">
                  <button
                    onClick={() => toggleProduct(p)}
                    className={`rounded-full px-3 py-1 text-xs font-black ${p.available ? "bg-emerald-100 text-emerald-700" : "bg-stone-200 text-stone-500"}`}
                  >
                    {p.available ? "✓ Tersedia" : "Habis"}
                  </button>
                  <div className="flex gap-1">
                    <button
                      onClick={() =>
                        setProdModal({
                          id: p.id,
                          category_id: p.category_id ?? 0,
                          name: p.name,
                          price: p.price,
                          description: p.description ?? "",
                          available: p.available,
                          is_active: p.is_active,
                        })
                      }
                      className="rounded-lg border border-stone-200 px-2 py-1 text-xs font-bold text-stone-600 hover:border-brand-300 hover:text-brand-700"
                    >
                      Edit
                    </button>
                    <button onClick={() => removeProduct(p)} className="rounded-lg border border-stone-200 px-2 py-1 text-xs font-bold text-stone-600 hover:border-red-300 hover:text-red-600">
                      Buang
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Category modal */}
      {catModal && (
        <Modal title={catModal.id ? "Edit kategori" : "Kategori baru"} onClose={() => setCatModal(null)}>
          <form onSubmit={saveCategory} className="space-y-4">
            <input required value={catModal.name} onChange={(e) => setCatModal({ ...catModal, name: e.target.value })} className={inputCls} placeholder="Nama kategori (cth: Nasi, Minuman)" />
            <input value={catModal.description} onChange={(e) => setCatModal({ ...catModal, description: e.target.value })} className={inputCls} placeholder="Penerangan (pilihan)" />
            <button type="submit" className={`${btnPrimary} w-full`}>Simpan</button>
          </form>
        </Modal>
      )}

      {/* Product modal */}
      {prodModal && (
        <Modal title={prodModal.id ? "Edit produk" : "Produk baru"} onClose={() => setProdModal(null)}>
          <form onSubmit={saveProduct} className="space-y-3">
            <div>
              <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Kategori</label>
              <select
                value={prodModal.category_id}
                onChange={(e) => setProdModal({ ...prodModal, category_id: Number(e.target.value) })}
                className={inputCls}
                required
              >
                <option value={0} disabled>Pilih kategori</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Nama</label>
              <input required value={prodModal.name} onChange={(e) => setProdModal({ ...prodModal, name: e.target.value })} className={inputCls} placeholder="Nasi Lemak Ayam" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Harga (RM)</label>
              <input required type="number" step="0.05" min="0" value={prodModal.price} onChange={(e) => setProdModal({ ...prodModal, price: e.target.value })} className={inputCls} placeholder="12.50" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-black uppercase tracking-wide text-stone-500">Penerangan</label>
              <textarea value={prodModal.description} onChange={(e) => setProdModal({ ...prodModal, description: e.target.value })} className={inputCls} rows={2} />
            </div>
            <label className="flex items-center gap-2 text-sm font-bold text-stone-700">
              <input type="checkbox" checked={prodModal.available} onChange={(e) => setProdModal({ ...prodModal, available: e.target.checked })} />
              Tersedia untuk dijual
            </label>
            <button type="submit" className={`${btnPrimary} w-full`}>Simpan</button>
          </form>
        </Modal>
      )}
    </>
  );
}

export default function MenuPage() {
  return (
    <AppShell active="menu">
      <MenuPageContent />
    </AppShell>
  );
}
