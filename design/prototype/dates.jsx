/* ============================================================
   Utilitaires dates (FR) — Les Mélèzes
   ============================================================ */
const MONTHS = ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"];
const MONTHS_ABBR = ["jan","fév","mar","avr","mai","juin","juil","août","sep","oct","nov","déc"];
const DOW = ["lun","mar","mer","jeu","ven","sam","dim"];
const DOW_LONG = ["lundi","mardi","mercredi","jeudi","vendredi","samedi","dimanche"];

const TODAY = new Date(2026, 5, 2); // 2 juin 2026 (référence prototype)

function parseISO(s) { const [y,m,d] = s.split("-").map(Number); return new Date(y, m-1, d, 12); }
function fmtISO(dt) { return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,"0")}-${String(dt.getDate()).padStart(2,"0")}`; }
function addDays(dt, n) { const d = new Date(dt); d.setDate(d.getDate()+n); return d; }
function startOfDay(dt) { const d = new Date(dt); d.setHours(12,0,0,0); return d; }
function sameDay(a, b) { return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate(); }
function dayDiff(a, b) { return Math.round((startOfDay(b) - startOfDay(a)) / 86400000); }
// lundi = 0
function dowMon(dt) { return (dt.getDay() + 6) % 7; }
function mondayOf(dt) { return addDays(startOfDay(dt), -dowMon(dt)); }
function nights(res) { return dayDiff(parseISO(res.start), parseISO(res.end)); }
function fmtRange(res) {
  const s = parseISO(res.start), e = addDays(parseISO(res.end), -1);
  const sameMonth = s.getMonth() === e.getMonth();
  if (sameDay(s, e)) return `${s.getDate()} ${MONTHS_ABBR[s.getMonth()]}`;
  if (sameMonth) return `${s.getDate()}–${e.getDate()} ${MONTHS_ABBR[s.getMonth()]}`;
  return `${s.getDate()} ${MONTHS_ABBR[s.getMonth()]} – ${e.getDate()} ${MONTHS_ABBR[e.getMonth()]}`;
}

// Réservation active à une date donnée
function resOnDate(reservations, dt) {
  return reservations.filter((r) => {
    const s = parseISO(r.start), e = parseISO(r.end);
    return startOfDay(dt) >= s && startOfDay(dt) < e;
  });
}

// Segments d'une semaine : pour chaque réservation chevauchant [weekStart, weekStart+7[
// renvoie {res, startCol, span} et assigne des "lanes" (empilement vertical).
function weekSegments(reservations, weekStart) {
  const weekEnd = addDays(weekStart, 7);
  const overlap = reservations
    .filter((r) => parseISO(r.start) < weekEnd && parseISO(r.end) > weekStart)
    .sort((a, b) => parseISO(a.start) - parseISO(b.start) || nights(b) - nights(a));
  const lanes = []; // chaque lane = endCol du dernier segment
  return overlap.map((r) => {
    const s = parseISO(r.start) < weekStart ? weekStart : parseISO(r.start);
    const e = parseISO(r.end) > weekEnd ? weekEnd : parseISO(r.end);
    const startCol = dayDiff(weekStart, s);
    const span = Math.max(1, dayDiff(s, e));
    const contL = parseISO(r.start) < weekStart;
    const contR = parseISO(r.end) > weekEnd;
    let lane = lanes.findIndex((end) => end <= startCol);
    if (lane === -1) { lane = lanes.length; lanes.push(0); }
    lanes[lane] = startCol + span;
    return { res: r, startCol, span, lane, contL, contR };
  });
}

Object.assign(window, {
  MONTHS, MONTHS_ABBR, DOW, DOW_LONG, TODAY,
  parseISO, fmtISO, addDays, startOfDay, sameDay, dayDiff, dowMon, mondayOf,
  nights, fmtRange, resOnDate, weekSegments,
});
