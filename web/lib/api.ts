import type { ReceiptData } from "../components/dashboard/printReceipt";

export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
export const APP_URL = process.env.NEXT_PUBLIC_APP_URL ?? "https://app.sajio.my";

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

export interface User {
  id: number;
  name: string;
  email: string;
  role?: string;
  restaurant_id?: number | null;
  created_at: string;
  updated_at: string;
}

export interface Restaurant {
  id: number;
  name: string;
  subdomain: string;
  currency?: string;
  timezone?: string;
  trial_ends_at?: string | null;
}

export interface AttendanceInfo {
  id: number;
  work_date: string;
  clock_in_at: string | null;
  clock_out_at: string | null;
  status: "on_duty" | "completed";
  worked_minutes?: number | null;
}

export interface PackageInfo {
  id: number;
  name: string;
  slug: string;
  description?: string;
  price_monthly: string;
}

export interface BillingStatus {
  status: string;
  status_label: string;
  can_operate: boolean;
  needs_subscription: boolean;
  is_subscribed: boolean;
  package: { id: number; name: string; price_monthly: string } | null;
  trial: { is_on_trial: boolean; ends_at: string | null; days_remaining: number };
  stripe_subscription: { stripe_status: string; renews_at: number | null } | null;
}

/* ---- Phase 3/4: menu, tables, sessions, orders, payments, receipts ---- */

export interface CategoryInfo {
  id: number;
  name: string;
  description?: string | null;
  sort_order?: number;
  is_active: boolean;
  products_count?: number;
}

export interface ProductInfo {
  id: number;
  category_id?: number;
  category?: { id: number; name: string } | null;
  name: string;
  description?: string | null;
  price: string;
  sku?: string | null;
  image_url?: string | null;
  is_active: boolean;
  available: boolean;
  sort_order?: number;
}

export interface TableInfo {
  id: number;
  number: string;
  capacity?: number;
  is_active: boolean;
  public_token?: string;
}

export interface SessionInfo {
  id: number;
  status: "open" | "closed";
  table?: { id: number; number: string } | null;
  opened_at?: string | null;
  closed_at?: string | null;
  opened_by?: number | null;
  closed_by?: number | null;
  total_amount?: string | null;
}

export interface OrderItemInfo {
  id: number;
  product_id: number | null;
  name: string;
  unit_price: string;
  quantity: number;
  line_total: string;
  note?: string | null;
}

export interface OrderInfo {
  id: number;
  order_no: string;
  type: "dine_in" | "takeaway";
  type_label: string;
  status: string;
  status_label: string;
  source?: string;
  table?: { id: number; number: string } | null;
  table_session_id?: number | null;
  staff?: { id: number; name: string } | null;
  subtotal: string;
  discount: string;
  tax: string;
  total: string;
  items?: OrderItemInfo[] | null;
  created_at?: string | null;
  completed_at?: string | null;
}

export interface PaymentInfo {
  id: number;
  order_id?: number | null;
  table_session_id?: number | null;
  method: string;
  method_label: string;
  amount: string;
  reference?: string | null;
  note?: string | null;
  received_by?: number | null;
  paid_at?: string | null;
}

export interface PaymentsResponse {
  data: PaymentInfo[];
  meta: { current_page: number; last_page: number; total: number };
  summary: {
    count: number;
    total_amount: string;
    by_method: { method: string; method_label: string; count: number; amount: string }[];
  };
}

export interface OrderListResponse {
  data: OrderInfo[];
  meta: { current_page: number; last_page: number; total: number };
}

export interface BillResponse {
  table: { id: number; number: string };
  orders: OrderInfo[];
  bill_total: string;
}

export interface ReceiptResponse {
  receipt: ReceiptData;
}

export type Query = Record<string, string | number | boolean | undefined>;

function qs(params?: Query): string {
  if (!params) return "";
  const p = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== "") p.set(k, String(v));
  });
  const s = p.toString();
  return s ? `?${s}` : "";
}

/* ------------------------------------------------------------------ */
/*  Request helper                                                     */
/* ------------------------------------------------------------------ */

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.headers ?? {}),
    },
    cache: "no-store",
  });

  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    try {
      const body = await res.json();
      if (typeof body.message === "string") message = body.message;
      if (body.errors) {
        const first = Object.values(body.errors)[0] as string[] | undefined;
        if (first?.[0]) message = first[0];
      }
    } catch {
      // response was not JSON; keep the generic message
    }
    throw new Error(message);
  }

  return res.json() as Promise<T>;
}

function authHeaders(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}` };
}

/* ------------------------------------------------------------------ */
/*  API                                                                */
/* ------------------------------------------------------------------ */

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  restaurant_name: string;
  subdomain: string;
  coupon_code?: string;
}

export interface VerifyResponse {
  message: string;
  user: User;
  restaurant: Restaurant;
  token: string;
}

export interface RegisterResponse {
  message: string;
  user: { id: number; name: string; email: string; role: string };
  restaurant: { id: number; name: string; subdomain: string };
  dev_otp: string | null;
  otp_expires_in_minutes: number;
}

export interface ProductInput {
  category_id: number;
  name: string;
  price: number;
  description?: string;
  is_active?: boolean;
  available?: boolean;
  sku?: string;
}

export const api = {
  registerV2(data: RegisterData) {
    return request<RegisterResponse>("/api/v1/register", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  verifyOtp(email: string, code: string) {
    return request<VerifyResponse>("/api/v1/verify-otp", {
      method: "POST",
      body: JSON.stringify({ email, code }),
    });
  },

  resendOtp(email: string) {
    return request<{ message: string; dev_otp: string | null }>("/api/v1/resend-otp", {
      method: "POST",
      body: JSON.stringify({ email }),
    });
  },

  checkSubdomain(subdomain: string) {
    return request<{ subdomain: string; available: boolean }>(
      `/api/v1/check-subdomain?subdomain=${encodeURIComponent(subdomain)}`,
    );
  },

  login(data: { email: string; password: string }) {
    return request<{ user: User; token: string }>("/api/v1/auth/login", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  me(token: string) {
    return request<User>("/api/v1/auth/me", {
      headers: authHeaders(token),
    });
  },

  logout(token: string) {
    return request<{ message: string }>("/api/v1/auth/logout", {
      method: "POST",
      headers: authHeaders(token),
    });
  },

  billingStatus(token: string) {
    return request<BillingStatus>("/api/v1/billing/status", {
      headers: authHeaders(token),
    });
  },

  packages() {
    return request<{ packages: PackageInfo[] }>("/api/v1/billing/packages");
  },

  checkout(token: string, packageId: number) {
    return request<{ checkout_url: string; session_id: string }>("/api/v1/billing/checkout", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ package_id: packageId }),
    });
  },

  billingPortal(token: string) {
    return request<{ url: string }>("/api/v1/billing/portal", {
      method: "POST",
      headers: authHeaders(token),
    });
  },

  attendanceToday(token: string) {
    return request<{ date: string; requires_attendance: boolean; on_duty: boolean; attendance: AttendanceInfo | null }>(
      "/api/v1/attendance/today",
      { headers: authHeaders(token) },
    );
  },

  clockIn(token: string) {
    return request<{ message: string; attendance: AttendanceInfo }>("/api/v1/attendance/clock-in", {
      method: "POST",
      headers: authHeaders(token),
    });
  },

  clockOut(token: string) {
    return request<{ message: string; attendance: AttendanceInfo }>("/api/v1/attendance/clock-out", {
      method: "POST",
      headers: authHeaders(token),
    });
  },

  /* ---- Profile & menu ---- */

  profile(token: string) {
    return request<{ restaurant: Restaurant; settings: Record<string, unknown>; branding: Record<string, unknown> }>(
      "/api/v1/profile",
      { headers: authHeaders(token) },
    );
  },

  categories(token: string) {
    return request<{ categories: CategoryInfo[] }>("/api/v1/menu/categories", {
      headers: authHeaders(token),
    });
  },

  createCategory(token: string, data: { name: string; description?: string; sort_order?: number; is_active?: boolean }) {
    return request<{ category: CategoryInfo }>("/api/v1/menu/categories", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  updateCategory(
    token: string,
    id: number,
    data: { name?: string; description?: string; sort_order?: number; is_active?: boolean },
  ) {
    return request<{ category: CategoryInfo }>(`/api/v1/menu/categories/${id}`, {
      method: "PUT",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  deleteCategory(token: string, id: number) {
    return request<{ message: string }>(`/api/v1/menu/categories/${id}`, {
      method: "DELETE",
      headers: authHeaders(token),
    });
  },

  products(token: string, params?: Query) {
    return request<{ data: ProductInfo[]; total?: number }>(`/api/v1/menu/products${qs(params)}`, {
      headers: authHeaders(token),
    });
  },

  createProduct(token: string, data: ProductInput) {
    return request<{ product: ProductInfo }>("/api/v1/menu/products", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  updateProduct(token: string, id: number, data: Partial<ProductInput>) {
    return request<{ product: ProductInfo }>(`/api/v1/menu/products/${id}`, {
      method: "PUT",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  deleteProduct(token: string, id: number) {
    return request<{ message: string }>(`/api/v1/menu/products/${id}`, {
      method: "DELETE",
      headers: authHeaders(token),
    });
  },

  /* ---- Tables & sessions ---- */

  tables(token: string) {
    return request<{ tables: TableInfo[] }>("/api/v1/tables", { headers: authHeaders(token) });
  },

  createTable(token: string, data: { number: string; capacity?: number; is_active?: boolean }) {
    return request<{ table: TableInfo }>("/api/v1/tables", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  bulkTables(token: string, data: { from: number; to: number; capacity?: number }) {
    return request<{ tables: TableInfo[]; count: number }>("/api/v1/tables/bulk", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  updateTable(token: string, id: number, data: { number?: string; capacity?: number; is_active?: boolean }) {
    return request<{ table: TableInfo }>(`/api/v1/tables/${id}`, {
      method: "PUT",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  deleteTable(token: string, id: number) {
    return request<{ message: string }>(`/api/v1/tables/${id}`, {
      method: "DELETE",
      headers: authHeaders(token),
    });
  },

  regenerateTableToken(token: string, id: number) {
    return request<{ table: TableInfo }>(`/api/v1/tables/${id}/regenerate-token`, {
      method: "POST",
      headers: authHeaders(token),
    });
  },

  openSessions(token: string) {
    return request<{ sessions: SessionInfo[] }>("/api/v1/sessions/open-sessions", {
      headers: authHeaders(token),
    });
  },

  openSession(token: string, tableId: number) {
    return request<{ session: SessionInfo }>("/api/v1/sessions/open", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ table_id: tableId }),
    });
  },

  sessionForTable(token: string, tableId: number) {
    return request<{ session: SessionInfo | null }>(`/api/v1/sessions/table/${tableId}`, {
      headers: authHeaders(token),
    });
  },

  settleSession(
    token: string,
    sessionId: number,
    data: { method?: string; amount?: number; reference?: string; note?: string },
  ) {
    return request<{ session: SessionInfo; payment: PaymentInfo | null; orders: OrderInfo[] }>(
      `/api/v1/sessions/${sessionId}/close`,
      {
        method: "POST",
        headers: authHeaders(token),
        body: JSON.stringify(data),
      },
    );
  },

  /* ---- Orders ---- */

  createOrder(
    token: string,
    payload: {
      type: "dine_in" | "takeaway";
      table_id?: number;
      items: { product_id: number; quantity: number; note?: string }[];
      discount?: number;
      note?: string;
      customer_name?: string;
    },
  ) {
    return request<{ message: string; order: OrderInfo }>("/api/v1/orders", {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(payload),
    });
  },

  ordersIndex(token: string, params?: Query) {
    return request<OrderListResponse>(`/api/v1/orders${qs(params)}`, { headers: authHeaders(token) });
  },

  orderShow(token: string, id: number) {
    return request<{ order: OrderInfo }>(`/api/v1/orders/${id}`, { headers: authHeaders(token) });
  },

  orderStatus(token: string, id: number, status: string, reason?: string) {
    return request<{ message: string; order: OrderInfo }>(`/api/v1/orders/${id}/status`, {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ status, reason }),
    });
  },

  tableCurrent(token: string, tableId: number) {
    return request<BillResponse>(`/api/v1/orders/table/${tableId}/current`, { headers: authHeaders(token) });
  },

  payOrder(token: string, orderId: number, data: { method: string; amount?: number; reference?: string; note?: string }) {
    return request<{ payment: PaymentInfo; order: OrderInfo }>(`/api/v1/orders/${orderId}/pay`, {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(data),
    });
  },

  /* ---- Sales & receipts ---- */

  paymentsIndex(token: string, params?: Query) {
    return request<PaymentsResponse>(`/api/v1/payments${qs(params)}`, { headers: authHeaders(token) });
  },

  orderReceipt(token: string, orderId: number) {
    return request<ReceiptResponse>(`/api/v1/orders/${orderId}/receipt`, { headers: authHeaders(token) });
  },

  sessionReceipt(token: string, sessionId: number) {
    return request<ReceiptResponse>(`/api/v1/sessions/${sessionId}/receipt`, { headers: authHeaders(token) });
  },
};
