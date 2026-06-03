/* ============================================================
   Planning — conteneur + modal réservation
   ============================================================ */

function ResModal({ open, initial, onClose, onSave, onDelete }) {
  const editing = initial && initial.id;
  const [occId, setOccId] = React.useState("cm");
  const [start, setStart] = React.useState("");
  const [end, setEnd] = React.useState("");
  const [guests, setGuests] = React.useState(2);
  const [note, setNote] = React.useState("");
  const [err, setErr] = React.useState("");

  React.useEffect(() => {
    if (!open) return;
    setErr("");
    if (initial && initial.id) {
      setOccId(initial.occ); setStart(initial.start); setEnd(initial.end);
      setGuests(initial.guests); setNote(initial.note || "");
    } else if (initial) {
      setOccId("cm"); setStart(initial.start); setEnd(initial.end); setGuests(2); setNote("");
    }
  }, [open, initial]);

  if (!open) return null;
  const save = () => {
    if (!start || !end) { setErr("Renseignez les dates."); return; }
    if (parseISO(end) <= parseISO(start)) { setErr("Le départ doit suivre l'arrivée."); return; }
    onSave({ id: editing ? initial.id : "r" + Date.now(), occ: occId, start, end, guests: Number(guests), note });
    onClose();
  };
  const n = (start && end && parseISO(end) > parseISO(start)) ? dayDiff(parseISO(start), parseISO(end)) : 0;

  return (
    <div className="modal-scrim" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <div>
            <div className="eyebrow">{editing ? "Modifier le séjour" : "Nouveau séjour"}</div>
            <h2 style={{ fontSize: 21, marginTop: 4 }}>{editing ? "Détails de l'occupation" : "Réserver l'appartement"}</h2>
          </div>
          <button className="btn icon ghost" onClick={onClose}><Icon name="x" size={18} /></button>
        </div>

        <div className="modal-body">
          <label className="fld-label">Qui occupe&nbsp;?</label>
          <div className="occ-pick">
            {OCCUPANTS.map((o) => (
              <button key={o.id} className={"occ-opt" + (occId === o.id ? " on" : "")}
                      style={occId === o.id ? { borderColor: o.color, background: o.bg } : {}}
                      onClick={() => setOccId(o.id)}>
                <Avatar o={o} size={28} />
                <div style={{ textAlign: "left", lineHeight: 1.2 }}>
                  <div style={{ fontWeight: 700, fontSize: 13 }}>{o.name}</div>
                  <div className="muted" style={{ fontSize: 11 }}>{o.role}</div>
                </div>
              </button>
            ))}
          </div>

          <div className="fld-grid">
            <div>
              <label className="fld-label">Arrivée</label>
              <input type="date" className="fld" value={start} onChange={(e) => setStart(e.target.value)} />
            </div>
            <div>
              <label className="fld-label">Départ</label>
              <input type="date" className="fld" value={end} onChange={(e) => setEnd(e.target.value)} />
            </div>
            <div>
              <label className="fld-label">Personnes</label>
              <div className="stepper">
                <button onClick={() => setGuests(Math.max(1, guests - 1))}><Icon name="x" size={13} style={{ transform: "rotate(45deg)" }} /></button>
                <span className="num">{guests}</span>
                <button onClick={() => setGuests(Math.min(12, guests + 1))}><Icon name="plus" size={14} /></button>
              </div>
            </div>
          </div>

          <label className="fld-label">Note <span className="muted" style={{ fontWeight: 400 }}>· motif, infos pratiques</span></label>
          <textarea className="fld" rows={2} value={note} placeholder="Ex. semaine de ski, prêt aux amis…"
                    onChange={(e) => setNote(e.target.value)} />

          {n > 0 ? <div className="modal-summary"><Icon name="clock" size={15} /><span><b className="num">{n}</b> nuit{n > 1 ? "s" : ""} · {occ(occId).name}</span></div> : null}
          {err ? <div className="modal-err"><Icon name="alert" size={15} />{err}</div> : null}
        </div>

        <div className="modal-foot">
          {editing ? <button className="btn ghost" style={{ color: "var(--replace)", marginRight: "auto" }}
                             onClick={() => { onDelete(initial.id); onClose(); }}><Icon name="trash" size={16} />Supprimer</button> : null}
          <button className="btn" onClick={onClose}>Annuler</button>
          <button className="btn primary" onClick={save}><Icon name="check" size={16} />{editing ? "Enregistrer" : "Réserver"}</button>
        </div>
      </div>
    </div>
  );
}

function Planning({ reservations, onSave, onDelete, calView, setCalView, density }) {
  const [focus, setFocus] = React.useState(new Date(TODAY));
  const [modal, setModal] = React.useState({ open: false, initial: null });
  const openNew = (day) => setModal({ open: true, initial: { start: fmtISO(day), end: fmtISO(addDays(day, 1)) } });
  const openRes = (res) => setModal({ open: true, initial: res });

  const title = calView === "month"
    ? `${MONTHS[focus.getMonth()]} ${focus.getFullYear()}`
    : calView === "week"
    ? (() => { const ws = mondayOf(focus), we = addDays(ws, 6);
        return ws.getMonth() === we.getMonth()
          ? `${ws.getDate()} – ${we.getDate()} ${MONTHS[ws.getMonth()]}`
          : `${ws.getDate()} ${MONTHS_ABBR[ws.getMonth()]} – ${we.getDate()} ${MONTHS_ABBR[we.getMonth()]}`; })()
    : "Réservations";

  const nav = (dir) => {
    if (calView === "month") setFocus(new Date(focus.getFullYear(), focus.getMonth() + dir, 1, 12));
    else if (calView === "week") setFocus(addDays(focus, dir * 7));
  };

  // occupant courant
  const current = resOnDate(reservations, TODAY)[0];
  const next = [...reservations].filter((r) => parseISO(r.start) > TODAY).sort((a, b) => parseISO(a.start) - parseISO(b.start))[0];

  return (
    <div className="main view">
      <Topbar eyebrow="Planning d'occupation" title={title}>
        {calView !== "list" ? (
          <div className="cal-nav">
            <button className="btn icon sm" onClick={() => nav(-1)}><Icon name="chevL" size={16} /></button>
            <button className="btn sm" onClick={() => setFocus(new Date(TODAY))}>Aujourd'hui</button>
            <button className="btn icon sm" onClick={() => nav(1)}><Icon name="chevR" size={16} /></button>
          </div>
        ) : null}
        <div className="seg">
          {[["month","grid","Mois"],["week","cols","Semaine"],["list","list","Liste"]].map(([v, ic, lb]) => (
            <button key={v} className={calView === v ? "on" : ""} onClick={() => setCalView(v)}>
              <Icon name={ic} size={14} />{lb}
            </button>
          ))}
        </div>
        <button className="btn primary" onClick={() => openNew(TODAY)}><Icon name="plus" size={16} />Réserver</button>
      </Topbar>

      <div className="content">
        <div className="content-inner">
          <div className="occ-status">
            {current
              ? <div className="status-card"><span className="status-dot" style={{ background: occ(current.occ).color }} />
                  <span>Actuellement&nbsp;: <b>{occ(current.occ).name}</b> <span className="muted">· départ le {addDays(parseISO(current.end), -1).getDate()} {MONTHS_ABBR[parseISO(current.end).getMonth()]}</span></span></div>
              : <div className="status-card"><span className="status-dot" style={{ background: "var(--sage)" }} />
                  <span>Appartement <b>libre</b> aujourd'hui</span></div>}
            {next
              ? <div className="status-card subtle"><Icon name="arrow" size={15} className="muted" />
                  <span>Prochain&nbsp;: <b>{occ(next.occ).name}</b> <span className="muted">· {fmtRange(next)}</span></span></div>
              : null}
          </div>

          <OccLegend reservations={reservations} />

          {calView === "month" && <MonthView reservations={reservations} focus={focus} density={density} onPick={openNew} onOpen={openRes} />}
          {calView === "week" && <WeekView reservations={reservations} focus={focus} onPick={openNew} onOpen={openRes} />}
          {calView === "list" && <ListView reservations={reservations} onOpen={openRes} />}
        </div>
      </div>

      <ResModal open={modal.open} initial={modal.initial}
                onClose={() => setModal({ open: false, initial: null })}
                onSave={onSave} onDelete={onDelete} />
    </div>
  );
}

Object.assign(window, { Planning, ResModal });
