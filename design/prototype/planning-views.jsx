/* ============================================================
   Module Planning — calendrier mois / semaine / liste
   ============================================================ */

function OccDot({ o, size = 8 }) {
  return <span style={{ width: size, height: size, borderRadius: 999, background: o.color, display: "inline-block", flexShrink: 0 }} />;
}
function Avatar({ o, size = 26 }) {
  return <span className="avatar" style={{ width: size, height: size, background: o.color, fontSize: size * 0.42 }}>{o.short}</span>;
}

function OccLegend({ reservations }) {
  return (
    <div style={{ display: "flex", flexWrap: "wrap", gap: 14, padding: "0 2px 16px" }}>
      {OCCUPANTS.map((o) => (
        <div key={o.id} style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, color: "var(--ink-2)" }}>
          <OccDot o={o} /><span style={{ fontWeight: 600 }}>{o.name}</span>
          <span className="muted" style={{ fontSize: 11.5 }}>· {o.role}</span>
        </div>
      ))}
    </div>
  );
}

/* ---------- Vue MOIS ---------- */
function MonthView({ reservations, focus, density, onPick, onOpen }) {
  const maxLanes = { compact: 2, regular: 3, comfy: 4 }[density] || 3;
  const monthStart = new Date(focus.getFullYear(), focus.getMonth(), 1, 12);
  const gridStart = mondayOf(monthStart);
  const weeks = [];
  for (let w = 0; w < 6; w++) weeks.push(addDays(gridStart, w * 7));

  return (
    <div className="cal-month card">
      <div className="cal-dow">
        {DOW.map((d) => <div key={d} className="cal-dow-cell">{d}</div>)}
      </div>
      <div className="cal-grid">
        {weeks.map((ws, wi) => {
          const segs = weekSegments(reservations, ws);
          const hidden = {}; // colonne -> nb cachés
          segs.forEach((s) => {
            if (s.lane >= maxLanes) for (let c = s.startCol; c < s.startCol + s.span; c++) hidden[c] = (hidden[c] || 0) + 1;
          });
          return (
            <div key={wi} className="cal-week" style={{ minHeight: "var(--cell-h)" }}>
              {Array.from({ length: 7 }).map((_, ci) => {
                const day = addDays(ws, ci);
                const out = day.getMonth() !== focus.getMonth();
                const today = sameDay(day, TODAY);
                return (
                  <div key={ci} className={"cal-cell" + (out ? " out" : "") + (today ? " today" : "")}
                       onClick={() => onPick(day)}>
                    <span className="cal-daynum">{day.getDate()}</span>
                    {hidden[ci] ? <span className="cal-more">+{hidden[ci]}</span> : null}
                  </div>
                );
              })}
              <div className="cal-bars">
                {segs.filter((s) => s.lane < maxLanes).map((s) => (
                  <button key={s.res.id} className="resbar"
                          style={{
                            left: `calc(${(s.startCol / 7) * 100}% + 3px)`,
                            width: `calc(${(s.span / 7) * 100}% - 6px)`,
                            top: `calc(26px + ${s.lane} * 22px)`,
                            background: occ(s.res.occ).bg, color: occ(s.res.occ).color,
                            borderTopLeftRadius: s.contL ? 0 : 6, borderBottomLeftRadius: s.contL ? 0 : 6,
                            borderTopRightRadius: s.contR ? 0 : 6, borderBottomRightRadius: s.contR ? 0 : 6,
                            borderLeft: `3px solid ${occ(s.res.occ).color}`,
                          }}
                          onClick={(e) => { e.stopPropagation(); onOpen(s.res); }}>
                    <span className="resbar-name">{occ(s.res.occ).name}</span>
                  </button>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* ---------- Vue SEMAINE ---------- */
function WeekView({ reservations, focus, onPick, onOpen }) {
  const ws = mondayOf(focus);
  const segs = weekSegments(reservations, ws);
  const laneCount = Math.max(1, ...segs.map((s) => s.lane + 1));
  return (
    <div className="cal-weekview card">
      <div className="wk-head">
        {Array.from({ length: 7 }).map((_, ci) => {
          const day = addDays(ws, ci);
          const today = sameDay(day, TODAY);
          return (
            <div key={ci} className={"wk-head-cell" + (today ? " today" : "")}>
              <span className="wk-dow">{DOW[ci]}</span>
              <span className="wk-date">{day.getDate()}</span>
            </div>
          );
        })}
      </div>
      <div className="wk-body" style={{ minHeight: laneCount * 62 + 24 }}>
        <div className="wk-cols">
          {Array.from({ length: 7 }).map((_, ci) => {
            const day = addDays(ws, ci);
            return <div key={ci} className={"wk-col" + (sameDay(day, TODAY) ? " today" : "")} onClick={() => onPick(day)} />;
          })}
        </div>
        <div className="wk-bars">
          {segs.map((s) => {
            const o = occ(s.res.occ);
            return (
              <button key={s.res.id} className="wk-bar"
                      style={{
                        left: `calc(${(s.startCol / 7) * 100}% + 4px)`,
                        width: `calc(${(s.span / 7) * 100}% - 8px)`,
                        top: 12 + s.lane * 62, background: o.bg, borderLeft: `3px solid ${o.color}`,
                      }}
                      onClick={(e) => { e.stopPropagation(); onOpen(s.res); }}>
                <span className="wk-bar-top"><Avatar o={o} size={20} /><b style={{ color: o.color }}>{o.name}</b>
                  <span className="wk-bar-g"><Icon name="users" size={12} />{s.res.guests}</span></span>
                <span className="wk-bar-note">{s.res.note}</span>
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}

/* ---------- Vue LISTE ---------- */
function ListView({ reservations, onOpen }) {
  const sorted = [...reservations].sort((a, b) => parseISO(a.start) - parseISO(b.start));
  const groups = {};
  sorted.forEach((r) => {
    const s = parseISO(r.start); const key = `${s.getFullYear()}-${s.getMonth()}`;
    (groups[key] = groups[key] || { label: `${MONTHS[s.getMonth()]} ${s.getFullYear()}`, items: [] }).items.push(r);
  });
  return (
    <div className="cal-list">
      {Object.entries(groups).map(([k, g]) => (
        <div key={k} className="list-group">
          <div className="list-month"><span>{g.label}</span></div>
          <div className="list-rows">
            {g.items.map((r) => {
              const o = occ(r.occ); const past = parseISO(r.end) <= TODAY;
              const current = parseISO(r.start) <= TODAY && parseISO(r.end) > TODAY;
              return (
                <button key={r.id} className={"list-row card" + (past ? " past" : "")} onClick={() => onOpen(r)}>
                  <div className="list-date">
                    <span className="list-d">{parseISO(r.start).getDate()}</span>
                    <span className="list-m">{MONTHS_ABBR[parseISO(r.start).getMonth()]}</span>
                  </div>
                  <div style={{ width: 3, alignSelf: "stretch", borderRadius: 3, background: o.color }} />
                  <Avatar o={o} size={34} />
                  <div className="list-main">
                    <div className="list-name">{o.name}
                      {current ? <span className="now-pill">en cours</span> : null}</div>
                    <div className="list-note">{r.note}</div>
                  </div>
                  <div className="list-meta">
                    <span className="mono">{fmtRange(r)}</span>
                    <span className="muted">{nights(r)} nuit{nights(r) > 1 ? "s" : ""} · {r.guests} pers.</span>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}

Object.assign(window, { OccDot, Avatar, OccLegend, MonthView, WeekView, ListView });
