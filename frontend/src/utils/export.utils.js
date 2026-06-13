import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import { applyPlugin } from 'jspdf-autotable';
import html2canvas from 'html2canvas';

// Manually apply the autoTable plugin to jsPDF (required for ESM/Vite environments)
applyPlugin(jsPDF);

/**
 * Export data to Excel (.xlsx)
 * @param {Array} headers - Array of header strings
 * @param {Array} rows - Array of arrays (row data)
 * @param {string} filename - Output filename without extension
 */
export function exportToExcel(headers, rows, filename = 'export') {
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

  // Auto-fit column widths
  const colWidths = headers.map((h, i) => {
    const maxLen = Math.max(
      h.length,
      ...rows.map(r => String(r[i] || '').length)
    );
    return { wch: Math.min(maxLen + 3, 40) };
  });
  ws['!cols'] = colWidths;

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
  XLSX.writeFile(wb, `${filename}.xlsx`);
}

/**
 * Build an HTML table string from headers and rows (for PDF/print).
 * Uses a Unicode-friendly font stack for Bengali/Bangla support.
 */
function buildTableHtml(title, headers, rows) {
  const headerCells = headers.map(h => `<th>${h}</th>`).join('');
  const bodyRows = rows.map(r =>
    `<tr>${r.map(c => `<td>${c ?? '—'}</td>`).join('')}</tr>`
  ).join('');

  return `
    <div id="pdf-export-root" style="font-family:'Segoe UI','Noto Sans Bengali','Arial Unicode MS',Arial,sans-serif;padding:20px;color:#333;">
      <h1 style="font-size:20px;margin-bottom:4px;color:#101828;text-align:center;">${title}</h1>
      <p style="font-size:11px;color:#98a2b3;text-align:center;margin-bottom:16px;">
        Generated: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
      </p>
      <table style="width:100%;border-collapse:collapse;font-size:11px;">
        <thead>
          <tr>${headerCells}</tr>
        </thead>
        <tbody>${bodyRows}</tbody>
      </table>
      <p style="margin-top:16px;font-size:9px;color:#98a2b3;text-align:center;">Coaching Management System</p>
    </div>
  `;
}

/**
 * Export data to PDF with full Unicode/Bengali font support.
 * Uses html2canvas to render the table in the browser (which handles
 * all Unicode characters including Bengali/Bangla), then embeds the
 * rendered image into the PDF.
 *
 * @param {string} title - Report title
 * @param {Array} headers - Array of header strings
 * @param {Array} rows - Array of arrays (row data)
 * @param {string} filename - Output filename without extension
 * @param {object} opts - Additional options
 */
export async function exportToPDF(title, headers, rows, filename = 'export', opts = {}) {
  // Build the HTML table
  const html = buildTableHtml(title, headers, rows);

  // Create a temporary container off-screen
  const container = document.createElement('div');
  container.innerHTML = html;
  container.style.position = 'absolute';
  container.style.left = '-9999px';
  container.style.top = '0';
  container.style.width = '1100px'; // ~landscape A4 width in px
  document.body.appendChild(container);

  try {
    // Render the container to a canvas using html2canvas
    const canvas = await html2canvas(container, {
      scale: 2, // Higher scale for better quality
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff',
    });

    const imgData = canvas.toDataURL('image/png');

    // Create PDF (landscape A4)
    const doc = new jsPDF('landscape', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();

    // Calculate image dimensions to fit the page
    const imgWidth = pageWidth - 16; // 8mm margins on each side
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    // If the image is taller than one page, we need multiple pages
    let heightLeft = imgHeight;
    let position = 8; // top margin

    // First page
    doc.addImage(imgData, 'PNG', 8, position, imgWidth, imgHeight);
    heightLeft -= (pageHeight - 16);

    // Add pages if content overflows
    while (heightLeft > 0) {
      position = -(pageHeight - 16) * (Math.ceil(imgHeight / (pageHeight - 16)) - 1) + (8 - (pageHeight - 16));
      doc.addPage();
      doc.addImage(imgData, 'PNG', 8, position, imgWidth, imgHeight);
      heightLeft -= (pageHeight - 16);
    }

    // Footer on each page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.setFontSize(7);
      doc.setTextColor(150);
      doc.text(
        `Page ${i} of ${pageCount}`,
        pageWidth - 15,
        doc.internal.pageSize.getHeight() - 8,
        { align: 'right' }
      );
    }

    doc.save(`${filename}.pdf`);
  } finally {
    // Clean up the temporary container
    document.body.removeChild(container);
  }
}

/**
 * Print a specific DOM element
 * @param {string} title - Print title
 * @param {Array} headers - Table headers
 * @param {Array} rows - Table rows (arrays)
 */
export function printTable(title, headers, rows) {
  const win = window.open('', '_blank');
  const styles = `
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #333; }
      h1 { font-size: 20px; margin-bottom: 4px; color: #101828; }
      .subtitle { font-size: 12px; color: #98a2b3; margin-bottom: 20px; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      th { background: #4a90d9; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
      td { padding: 7px 10px; border-bottom: 1px solid #eee; }
      tr:nth-child(even) td { background: #f9fafb; }
      tr:hover td { background: #eff8ff; }
      .footer { margin-top: 20px; font-size: 10px; color: #98a2b3; text-align: center; }
      @media print {
        body { padding: 10px; }
        .no-print { display: none; }
      }
    </style>
  `;

  const headerRow = headers.map(h => `<th>${h}</th>`).join('');
  const bodyRows = rows.map(r =>
    `<tr>${r.map(c => `<td>${c ?? '—'}</td>`).join('')}</tr>`
  ).join('');

  win.document.write(`
    <!DOCTYPE html>
    <html>
    <head><title>${title}</title>${styles}</head>
    <body>
      <h1>${title}</h1>
      <p class="subtitle">Generated: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
      <table>
        <thead><tr>${headerRow}</tr></thead>
        <tbody>${bodyRows}</tbody>
      </table>
      <p class="footer">Coaching Management System</p>
      <div class="no-print" style="text-align:center;margin-top:20px">
        <button onclick="window.print()" style="padding:8px 24px;background:#4a90d9;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨 Print</button>
      </div>
      <script>window.onload = function() { setTimeout(() => window.print(), 500); };<\/script>
    </body>
    </html>
  `);
  win.document.close();
}
