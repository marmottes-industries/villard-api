/* ============================================================
   Module Courses — consommables récurrents + liste générée
   ============================================================ */

const needsRestock = (c) => c.flag || c.stock <= c.seuil;

function StockGauge({ c }) {
  const pct = Math.max(0, Math.min(100, (c.stock / c.max) * 100));
  const seuilPct = Math.min(100, (c.seuil / c.max) * 100);
  const level = c.stock <= c.seuil ? "low" : c.stock <= c.seuil * 2 ? "mid" : "ok";
  return (
    <div className="gauge">
      <div className="gauge-track">
        <div className={"gauge-fill " + level} style={{ width: pct + "%" }} />
        <div className="gauge-seuil" style={{ left: seuilPct + "%" }} title="Seuil de réappro" />
      </div>
      <span className="gauge-txt mono num">{c.stock}<span className="muted">/{c.max} {c.unit}</span></span>
    </div>
  );
}

function Courses({ courses, onPatch }) {
  const [filter, setFilter] = React.useState("all");
  const cats = [...new Set(courses.map((c) => c.cat))];
  const list = courses.filter(needsRestock);
  const shown = filter === "all" ? courses : courses.filter((c) => c.cat === filter);

  return (
    <div className="main view">
      <Topbar eyebrow="Consommables récurrents" title="Courses">
        <span className="topbar-sub mono" style={{ alignSelf: "center" }}>
          <b style={{ color: list.length ? "var(--wood-deep)" : "var(--sage)" }}>{list.length}</b> à racheter
        </span>
      </Topbar>

      <div className="content">
        <div className="content-inner crs-layout">
          {/* Consommables */}
          <div className="crs-main">
            <div className="filt-bar">
              <div className="chips">
                <button className={"chip" + (filter === "all" ? " on" : "")} onClick={() => setFilter("all")}>Tout</button>
                {cats.map((c) => (
                  <button key={c} className={"chip" + (filter === c ? " on" : "")} onClick={() => setFilter(c)}>{c}</button>
                ))}
              </div>
            </div>

            <div className="card crs-table">
              {shown.map((c) => {
                const need = needsRestock(c);
                return (
                  <div key={c.id} className={"crs-row" + (need ? " need" : "")}>
                    <div className="crs-info">
                      <span className="crs-name">{c.name}</span>
                      <span className="crs-meta"><span className="crs-cat">{c.cat}</span>
                        <span className="muted mono"><Icon name="refresh" size={11} />{c.recur}</span></span>
                    </div>
                    <StockGauge c={c} />
                    {need
                      ? <button className="btn sm" style={{ color: "var(--wood-deep)", borderColor: "var(--wood)" }}
                                onClick={() => onPatch(c.id, { stock: c.max, flag: false })}>
                          <Icon name="check" size={14} />Réappro.</button>
                      : <button className="btn sm ghost" onClick={() => onPatch(c.id, { flag: true })}>
                          <Icon name="plus" size={14} />Liste</button>}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Liste de courses */}
          <aside className="crs-list">
            <div className="crs-list-card card">
              <div className="crs-list-head">
                <div><div className="eyebrow">À rapporter</div><h3 style={{ fontSize: 17, marginTop: 3 }}>Liste de courses</h3></div>
                <span className="crs-count num">{list.length}</span>
              </div>

              {list.length ? (
                <div className="crs-list-body">
                  {list.map((c) => (
                    <label key={c.id} className="crs-check">
                      <input type="checkbox" onChange={() => onPatch(c.id, { stock: c.max, flag: false })} />
                      <span className="crs-check-box"><Icon name="check" size={13} /></span>
                      <span className="crs-check-name">{c.name}</span>
                      <span className="mono muted" style={{ fontSize: 11 }}>{c.cat}</span>
                    </label>
                  ))}
                </div>
              ) : (
                <div className="crs-empty"><Icon name="check" size={24} style={{ color: "var(--sage)" }} /><p>Tout est en stock.</p></div>
              )}

              <div className="crs-list-foot">
                <button className="btn sm" style={{ flex: 1 }}><Icon name="download" size={14} />Exporter</button>
                <button className="btn sm" style={{ flex: 1 }}><Icon name="arrow" size={14} />Envoyer</button>
              </div>
            </div>

            <div className="crs-note mono">
              <Icon name="lock" size={13} />
              Liste stockée localement — jamais partagée avec un tiers.
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Courses });
