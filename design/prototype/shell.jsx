/* ============================================================
   Shell — Sidebar de navigation
   ============================================================ */

function Sidebar({ route, setRoute, counts }) {
  const nav = [
    { id: "planning",  label: "Planning",   ico: "calendar" },
    { id: "inventaire",label: "Inventaire", ico: "box", badge: counts.invReplace, warn: true },
    { id: "courses",   label: "Courses",    ico: "cart", badge: counts.toBuy, warn: true },
  ];
  return (
    <aside className="sidebar">
      <div className="brand">
        <div className="brand-mark">
          <div className="brand-glyph" style={{ color: "#A9C9A0" }}>
            <Icon name="leaf" size={20} />
          </div>
          <div>
            <div className="brand-name">Les Mélèzes</div>
            <div className="brand-sub">Villard-de-Lans</div>
          </div>
        </div>
      </div>

      <nav className="nav">
        <div className="nav-label">Gestion</div>
        {nav.map((n) => (
          <button key={n.id} className={"nav-item" + (route === n.id ? " active" : "")}
                  onClick={() => setRoute(n.id)}>
            <Icon name={n.ico} size={18} className="nav-ico" />
            <span>{n.label}</span>
            {n.badge ? <span className={"badge" + (n.warn ? " warn" : "")}>{n.badge}</span> : null}
          </button>
        ))}

        <div className="nav-label">Projet</div>
        <button className={"nav-item" + (route === "archi" ? " active" : "")}
                onClick={() => setRoute("archi")}>
          <Icon name="server" size={18} className="nav-ico" />
          <span>Architecture</span>
        </button>
      </nav>

      <div className="side-foot">
        <div className="host-pill">
          <span className="host-dot" />
          <div className="host-text">
            <b>Auto-hébergé</b><br />
            home.lan · vos données
          </div>
        </div>
      </div>
    </aside>
  );
}

/* Topbar réutilisable — chaque vue passe titre / sous-titre / actions */
function Topbar({ eyebrow, title, sub, children }) {
  return (
    <header className="topbar">
      <div className="topbar-titles">
        {eyebrow ? <div className="eyebrow">{eyebrow}</div> : null}
        <h1>{title}</h1>
      </div>
      {sub ? <div className="topbar-sub" style={{ alignSelf: "flex-end", paddingBottom: 3 }}>{sub}</div> : null}
      <div className="topbar-actions">{children}</div>
    </header>
  );
}

Object.assign(window, { Sidebar, Topbar });
