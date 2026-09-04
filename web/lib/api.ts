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
};
