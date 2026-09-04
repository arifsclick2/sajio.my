import type { Restaurant, User } from "./api";

/**
 * Session storage shared across *.sajio.my.
 *
 * IMPORTANT: sajio.my (register/login) and app.sajio.my (dashboard) are
 * different origins, and localStorage is per-origin — a token saved on
 * sajio.my would never be seen by app.sajio.my. So we store the session in
 * a cookie scoped to `.sajio.my` (both hosts read it). localStorage is kept
 * as a fallback for localhost where a `.sajio.my` cookie cannot be set.
 */

const TOKEN_KEY = "sajio_token";
const SESSION_KEY = "sajio_session"; // JSON { user, restaurant }
const SESSION_DAYS = 30;

function cookieDomain(): string {
  if (typeof window === "undefined") return "";
  const host = window.location.hostname;
  // Only scope to the parent domain on real sajio.my hosts.
  return host.endsWith("sajio.my") ? "domain=.sajio.my" : "";
}

function setCookie(name: string, value: string, days: number): void {
  const d = new Date();
  d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
  const domain = cookieDomain();
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${d.toUTCString()}; path=/; ${domain}; SameSite=Lax`;
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return m ? decodeURIComponent(m[1]) : null;
}

function deleteCookie(name: string): void {
  const domain = cookieDomain();
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; ${domain}; SameSite=Lax`;
}

interface SessionPayload {
  user: User;
  restaurant?: Restaurant | null;
}

function safeParse<T>(raw: string | null): T | null {
  if (!raw) return null;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

function payload(): SessionPayload | null {
  const raw = getCookie(SESSION_KEY) ?? window.localStorage.getItem(SESSION_KEY);
  return safeParse<SessionPayload>(raw);
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return getCookie(TOKEN_KEY) ?? window.localStorage.getItem(TOKEN_KEY);
}

export function getStoredUser(): User | null {
  if (typeof window === "undefined") return null;
  const legacy = safeParse<User>(window.localStorage.getItem("sajio_user"));
  if (legacy) return legacy;
  return payload()?.user ?? null;
}

export function getStoredRestaurant(): Restaurant | null {
  if (typeof window === "undefined") return null;
  const legacy = safeParse<Restaurant>(window.localStorage.getItem("sajio_restaurant"));
  if (legacy) return legacy;
  return payload()?.restaurant ?? null;
}

export function saveSession(token: string, user: User, restaurant?: Restaurant | null) {
  const data: SessionPayload = { user, restaurant: restaurant ?? null };
  setCookie(TOKEN_KEY, token, SESSION_DAYS);
  setCookie(SESSION_KEY, JSON.stringify(data), SESSION_DAYS);
  // Keep localStorage in sync (localhost / non-cookie browsers).
  window.localStorage.setItem(TOKEN_KEY, token);
  window.localStorage.setItem(SESSION_KEY, JSON.stringify(data));
  window.localStorage.setItem("sajio_user", JSON.stringify(user));
  if (restaurant) {
    window.localStorage.setItem("sajio_restaurant", JSON.stringify(restaurant));
  }
}

export function clearSession() {
  deleteCookie(TOKEN_KEY);
  deleteCookie(SESSION_KEY);
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(SESSION_KEY);
  window.localStorage.removeItem("sajio_user");
  window.localStorage.removeItem("sajio_restaurant");
}

/** Dashboard lives on the app subdomain. */
export function dashboardUrl(path = "/dashboard"): string {
  const app = process.env.NEXT_PUBLIC_APP_URL ?? "https://app.sajio.my";
  return `${app}${path}`;
}
