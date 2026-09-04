interface ReceiptItem {
  name: string;
  unit_price: string;
  quantity: number;
  line_total: string;
  note?: string | null;
}

interface ReceiptOrder {
  order_no?: string;
  created_at?: string | null;
  items?: ReceiptItem[];
  subtotal?: string;
  discount?: string;
  tax?: string;
  total?: string;
}

interface ReceiptPayment {
  method_label: string;
  amount: string;
  reference?: string | null;
}

export interface ReceiptData {
  type?: string;
  restaurant?: {
    name?: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postcode?: string | null;
    phone?: string | null;
    receipt_header?: string | null;
    receipt_footer?: string | null;
  };
  order?: ReceiptOrder;
  orders?: ReceiptOrder[];
  session?: { table?: { number?: string } | null };
  table?: { number?: string } | null;
  payments?: ReceiptPayment[];
  total_paid?: string;
}

function esc(s: unknown): string {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;");
}

function rows(items: ReceiptItem[] | undefined): string {
  if (!items?.length) return "";
  return items
    .map(
      (i) =>
        `<tr><td>${esc(i.name)}${i.note ? `<br/><span class="muted">${esc(i.note)}</span>` : ""}</td>` +
        `<td class="r">${i.quantity} × ${esc(i.unit_price)}</td>` +
        `<td class="r">${esc(i.line_total)}</td></tr>`,
    )
    .join("");
}

function orderBlock(o: ReceiptOrder): string {
  const parts: string[] = [];
  if (o.order_no) parts.push(`<div class="center small"><b>#${esc(o.order_no)}</b></div>`);
  parts.push(
    `<table class="items">${rows(o.items)}</table>` +
      `<table class="totals">` +
      (o.discount && Number(o.discount) > 0 ? `<tr><td>Diskaun</td><td class="r">-${esc(o.discount)}</td></tr>` : "") +
      (o.tax && Number(o.tax) > 0 ? `<tr><td>Cukai</td><td class="r">${esc(o.tax)}</td></tr>` : "") +
      `<tr class="grand"><td>JUMLAH</td><td class="r">${esc(o.total)}</td></tr>` +
      `</table>`,
  );
  return parts.join("");
}

/**
 * Opens a print-friendly 80mm-style receipt in a new window from the
 * JSON returned by /orders/{id}/receipt or /sessions/{id}/receipt.
 */
export function printReceipt(data: ReceiptData): void {
  const r = data.restaurant ?? {};
  const lines: string[] = [];
  lines.push(`<div class="rc">`);
  lines.push(`<div class="center bold">${esc(r.name ?? "Restoran")}</div>`);
  const addr = [r.address, r.city, r.state, r.postcode].filter(Boolean).join(", ");
  if (addr) lines.push(`<div class="center muted">${esc(addr)}</div>`);
  if (r.phone) lines.push(`<div class="center muted">${esc(r.phone)}</div>`);
  if (r.receipt_header) lines.push(`<div class="center small">${esc(r.receipt_header)}</div>`);
  lines.push(`<hr/>`);

  if (data.type === "session") {
    if (data.session?.table?.number) lines.push(`<div class="center bold">MEJA ${esc(data.session.table.number)}</div>`);
    lines.push(`<div class="muted">${new Date().toLocaleString("en-MY")}</div>`);
    for (const o of data.orders ?? []) {
      lines.push(`<hr/>${orderBlock(o)}`);
    }
  } else {
    if (data.table?.number) lines.push(`<div class="center bold">MEJA ${esc(data.table.number)}</div>`);
    lines.push(`<div class="muted">${new Date().toLocaleString("en-MY")}</div>`);
    if (data.order) lines.push(orderBlock(data.order));
  }

  const pay = data.payments ?? [];
  lines.push(`<hr/>`);
  lines.push(
    `<table class="totals">` +
      pay
        .map(
          (p) =>
            `<tr><td>${esc(p.method_label)}${p.reference ? `<br/><span class="muted">${esc(p.reference)}</span>` : ""}</td><td class="r">${esc(p.amount)}</td></tr>`,
        )
        .join("") +
      `<tr class="grand"><td>DIBAYAR</td><td class="r">${esc(data.total_paid ?? "")}</td></tr>` +
      `</table>`,
  );
  if (r.receipt_footer) lines.push(`<div class="center muted small" style="margin-top:8px">${esc(r.receipt_footer)}</div>`);
  lines.push(`<div class="center muted small" style="margin-top:6px">sajio.my</div>`);
  lines.push(`</div>`);

  const html = `<!doctype html><html><head><meta charset="utf-8"><title>Resit</title>
<style>
  body{font-family:ui-monospace,'Cascadia Mono',Menlo,Consolas,monospace;width:80mm;margin:0 auto;padding:8px;font-size:12px;color:#111}
  .rc{width:100%}
  .center{text-align:center}
  .right{text-align:right}
  .bold{font-weight:800}
  .muted{color:#666}
  .small{font-size:10px}
  hr{border:none;border-top:1px dashed #999;margin:6px 0}
  table{width:100%;border-collapse:collapse}
  td{padding:2px 0;vertical-align:top}
  .r{text-align:right;white-space:nowrap}
  .grand{font-weight:800;font-size:14px;border-top:1px solid #111}
  .items td{padding:1px 0}
  @media print{body{width:auto}}
</style></head><body>${lines.join("")}<script>window.onload=function(){setTimeout(function(){window.print();},120)}<\/script></body></html>`;

  const w = window.open("", "_blank", "width=420,height=640");
  if (!w) {
    // Popup blocked — nothing else we can do silently.
    return;
  }
  w.document.open();
  w.document.write(html);
  w.document.close();
}
