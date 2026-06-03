/* ============================================================
   Page Architecture — angle portfolio
   Récit : découplage, auto-hébergement, ownership des données.
   ============================================================ */

function Node({ k, name, sub, accent }) {
  return (
    <div className="arc-node" style={accent ? { borderColor: "color-mix(in oklab, var(--accent) 40%, var(--line-2))" } : {}}>
      <span className="arc-node-k mono">{k}</span>
      <span className="arc-node-n">{name}</span>
      <span className="arc-node-s mono muted">{sub}</span>
    </div>
  );
}
function Flow() {
  return <div className="arc-flow"><span className="arc-line" /><Icon name="chevR" size={15} /></div>;
}

function Architecture() {
  const stack = [
    ["Frontend", "React · Vite — SPA statique"],
    ["API", "Node · Fastify — REST + JWT"],
    ["Base de données", "PostgreSQL 16 — volume chiffré"],
    ["Hébergement", "Raspberry Pi 5 · Docker Compose"],
    ["Réseau", "Caddy (TLS auto) · Tailscale"],
    ["Sauvegarde", "restic → disque local + NAS"],
  ];
  const principles = [
    { ico: "leaf",  t: "Sortie des GAFAM", d: "Ni Google Agenda, ni cloud propriétaire. Le planning d'occupation et les listes restent strictement privés, sur le réseau de la maison." },
    { ico: "lock",  t: "Ownership des données", d: "Export complet en un clic (JSON, iCal). Sauvegardes locales chiffrées, réversibilité totale. Aucune donnée ne quitte le foyer." },
    { ico: "server",t: "Découplé & portable", d: "Front et API séparés par un contrat d'API stable. Le même back-end alimente le web, un widget, ou demain une app mobile." },
  ];

  return (
    <div className="main view">
      <Topbar eyebrow="Note d'architecture" title="Vos données, chez vous.">
        <a className="btn" href="#" onClick={(e) => e.preventDefault()}><Icon name="download" size={16} />Schéma PDF</a>
      </Topbar>

      <div className="content">
        <div className="content-inner arc">
          <p className="arc-lede">
            <b>Les Mélèzes</b> est une application personnelle <b>découplée</b> et <b>auto-hébergée</b>.
            Elle gère l'occupation et l'inventaire de l'appartement familial sans dépendre d'un service tiers —
            une démonstration d'architecture moderne autant qu'un outil du quotidien.
          </p>

          {/* Diagramme */}
          <div className="arc-diagram card">
            <div className="arc-diagram-head">
              <div className="eyebrow">Flux de données</div>
              <span className="arc-badge mono"><span className="host-dot" />0 service tiers</span>
            </div>

            <div className="arc-clients">
              <Node k="CLIENTS" name="Appareils famille" sub="navigateur · mobile" />
              <div className="arc-flow vert"><span className="arc-line" /><span className="mono arc-proto">HTTPS · Tailscale</span></div>
            </div>

            <div className="arc-enclosure">
              <span className="arc-enclosure-tag mono"><Icon name="pin" size={12} />Maison · réseau domestique · Raspberry Pi 5</span>
              <div className="arc-row">
                <Node k="PROXY" name="Caddy" sub="TLS auto" />
                <Flow />
                <Node k="CLIENT" name="SPA React" sub="statique · Vite" accent />
                <Flow />
                <Node k="API" name="Fastify" sub="REST · JWT" accent />
                <Flow />
                <Node k="DONNÉES" name="PostgreSQL" sub="volume chiffré" accent />
              </div>
              <div className="arc-backup">
                <span className="arc-line dashed" />
                <span className="mono muted"><Icon name="download" size={12} />restic → sauvegarde locale chiffrée</span>
              </div>
            </div>
          </div>

          {/* Principes */}
          <div className="arc-principles">
            {principles.map((p) => (
              <div key={p.t} className="arc-card card">
                <div className="arc-card-ico"><Icon name={p.ico} size={20} /></div>
                <h3>{p.t}</h3>
                <p>{p.d}</p>
              </div>
            ))}
          </div>

          {/* Stack */}
          <div className="arc-stack">
            <div className="sec-head"><h2>Stack technique</h2><div className="eyebrow">six briques, un seul boîtier</div></div>
            <div className="card arc-stack-table">
              {stack.map(([k, v]) => (
                <div key={k} className="arc-stack-row">
                  <span className="arc-stack-k">{k}</span>
                  <span className="arc-stack-v mono">{v}</span>
                </div>
              ))}
            </div>
          </div>

          <div className="arc-foot mono">
            <Icon name="leaf" size={14} />
            Conçu comme pièce de portfolio — code, schéma et démarche disponibles sur demande.
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Architecture });
