/** RM formatting used across dashboard modules. */
export function rm(value: string | number | null | undefined): string {
  const n = typeof value === "number" ? value : Number(value ?? 0);
  return `RM${n.toFixed(2)}`;
}

/** ISO date -> e.g. 12:34 PM (local). */
export function timeOnly(iso?: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleTimeString("en-MY", { hour: "numeric", minute: "2-digit" });
}
