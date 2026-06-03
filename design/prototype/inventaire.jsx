/* ============================================================
   Module Inventaire
   ============================================================ */

const STATE_META = {
  ok:      { label: "Bon état",     cls: "ok" },
  worn:    { label: "Usé",          cls: "worn" },
  replace: { label: "À remplacer",  cls: "replace" },
};
const STATE_CYCLE = { ok: "worn", worn: "replace", replace: "ok" };

function Stepper({ value, onDec, onInc, min = 0 }) {
  return (
    <div className="stepper sm">
      <button onClick={onDec} disabled={value <= min}><Icon name="x" size={12} style={{ transform: "rotate(45deg)" }} /></button>
      <span className="num">{value}</span>
      <button onClick={onInc}><Icon name="plus" size={13} /></button>
    </div>
  );
}

function InvRow({ it, onPatch }) {
  const sm = STATE_META[it.state];
  return (
    <div className="inv-row">
      <div className="inv-main">
        <span className="inv-name">{it.name}</span>
        {it.note ? <span className="inv-note muted">{it.note}</span> : null}
      </div>
      <span className="inv-loc mono"><Icon name="pin" size={12} />{it.loc}</span>
      <button className={"tag " + sm.cls} title="Cliquer pour changer l'état"
              onClick={() => onPatch(it.id, { state: STATE_CYCLE[it.state] })}>
        <span className="dot" style={{ background: `var(--${sm.cls === "ok" ? "ok" : sm.cls === "worn" ? "worn" : "replace"})` }} />
        {sm.label}
      </button>
      <Stepper value={it.qty} min={0}
               onDec={() => onPatch(it.id, { qty: Math.max(0, it.qty - 1) })}
               onInc={() => onPatch(it.id, { qty: it.qty + 1 })} />
    </div>
  );
}

function Inventaire({ inventory, onPatch }) {
  const [cat, setCat] = React.useState("all");
  const [state, setState] = React.useState("all");
  const [q, setQ] = React.useState("");

  const counts = {
    total: inventory.reduce((s, i) => s + i.qty, 0),
    ok: inventory.filter((i) => i.state === "ok").length,
    worn: inventory.filter((i) => i.state === "worn").length,
    replace: inventory.filter((i) => i.state === "replace").length,
  };

  const filtered = inventory.filter((i) =>
    (cat === "all" || i.cat === cat) &&
    (state === "all" || i.state === state) &&
    (q === "" || (i.name + " " + i.loc + " " + i.note).toLowerCase().includes(q.toLowerCase())));

  const cats = cat === "all" ? INV_CATS : INV_CATS.filter((c) => c.id === cat);

  return (
    <div className="main view">
      <Topbar eyebrow="Suivi d'inventaire" title="Inventaire">
        <div className="searchbox">
          <Icon name="search" size={15} className="muted" />
          <input placeholder="Rechercher un article, un lieu…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
      </Topbar>

      <div className="content">
        <div className="content-inner">
          <div className="inv-summary">
            <div className="sumcard"><span className="sum-k mono">ARTICLES</span><span className="sum-v num">{counts.total}</span><span className="sum-s muted">{inventory.length} références</span></div>
            <div className="sumcard"><span className="sum-k mono" style={{ color: "var(--ok)" }}>BON ÉTAT</span><span className="sum-v num">{counts.ok}</span><span className="sum-s muted">références</span></div>
            <div className="sumcard"><span className="sum-k mono" style={{ color: "var(--worn)" }}>USÉ</span><span className="sum-v num">{counts.worn}</span><span className="sum-s muted">à surveiller</span></div>
            <div className="sumcard hot"><span className="sum-k mono" style={{ color: "var(--replace)" }}>À REMPLACER</span><span className="sum-v num">{counts.replace}</span><span className="sum-s muted">action requise</span></div>
          </div>

          <div className="filt-bar">
            <div className="chips">
              <button className={"chip" + (cat === "all" ? " on" : "")} onClick={() => setCat("all")}>Tout</button>
              {INV_CATS.map((c) => (
                <button key={c.id} className={"chip" + (cat === c.id ? " on" : "")} onClick={() => setCat(c.id)}>
                  <Icon name={c.ico} size={14} />{c.label}
                </button>
              ))}
            </div>
            <div className="seg" style={{ marginLeft: "auto" }}>
              {[["all","Tous"],["ok","Bon"],["worn","Usé"],["replace","À remplacer"]].map(([v, lb]) => (
                <button key={v} className={state === v ? "on" : ""} onClick={() => setState(v)}>{lb}</button>
              ))}
            </div>
          </div>

          {cats.map((c) => {
            const items = filtered.filter((i) => i.cat === c.id);
            if (!items.length) return null;
            return (
              <div key={c.id} className="inv-cat">
                <div className="inv-cat-head">
                  <Icon name={c.ico} size={17} /><h3>{c.label}</h3>
                  <span className="mono muted" style={{ marginLeft: "auto", fontSize: 11.5 }}>{items.length} réf.</span>
                </div>
                <div className="inv-cat-body card">
                  {items.map((it) => <InvRow key={it.id} it={it} onPatch={onPatch} />)}
                </div>
              </div>
            );
          })}
          {!filtered.length ? <div className="empty"><Icon name="search" size={26} className="muted" /><p>Aucun article ne correspond.</p></div> : null}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Inventaire, STATE_META });
