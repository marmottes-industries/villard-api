/* ============================================================
   App — état global, routage, Tweaks
   ============================================================ */

const ACCENTS = [
  "#2E4A39", // sapin (forêt)
  "#2C5159", // lagon / épicéa bleuté
  "#97653A", // bois
  "#4F6076", // ardoise
  "#6E4B5E", // myrtille
];

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "density": "regular",
  "calView": "month",
  "accent": "#2E4A39",
  "grain": true
}/*EDITMODE-END*/;

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);

  const [route, setRoute] = React.useState(() => localStorage.getItem("marmotte.route") || "planning");
  React.useEffect(() => { localStorage.setItem("marmotte.route", route); }, [route]);

  const [reservations, setReservations] = React.useState(RESERVATIONS);
  const [inventory, setInventory] = React.useState(INVENTORY);
  const [courses, setCourses] = React.useState(COURSES);

  const onSaveRes = (res) => setReservations((prev) => {
    const i = prev.findIndex((r) => r.id === res.id);
    if (i === -1) return [...prev, res];
    const next = [...prev]; next[i] = res; return next;
  });
  const onDeleteRes = (id) => setReservations((prev) => prev.filter((r) => r.id !== id));
  const onPatchInv = (id, patch) => setInventory((prev) => prev.map((i) => i.id === id ? { ...i, ...patch } : i));
  const onPatchCourse = (id, patch) => setCourses((prev) => prev.map((c) => c.id === id ? { ...c, ...patch } : c));

  const counts = {
    invReplace: inventory.filter((i) => i.state === "replace").length,
    toBuy: courses.filter((c) => c.flag || c.stock <= c.seuil).length,
  };

  // appliquer accent dynamique
  const accentVars = {
    "--accent": t.accent,
    "--accent-2": `color-mix(in oklab, ${t.accent}, white 12%)`,
    "--accent-deep": `color-mix(in oklab, ${t.accent}, black 24%)`,
    "--accent-bg": `color-mix(in oklab, ${t.accent} 13%, var(--card))`,
  };

  return (
    <div className={"app-root" + (t.grain ? " grain" : "")} data-density={t.density} style={accentVars}>
      <Sidebar route={route} setRoute={setRoute} counts={counts} />

      {route === "planning" && (
        <Planning reservations={reservations} onSave={onSaveRes} onDelete={onDeleteRes}
                  calView={t.calView} setCalView={(v) => setTweak("calView", v)} density={t.density} />
      )}
      {route === "inventaire" && <Inventaire inventory={inventory} onPatch={onPatchInv} />}
      {route === "courses" && <Courses courses={courses} onPatch={onPatchCourse} />}
      {route === "archi" && <Architecture />}

      <TweaksPanel title="Tweaks">
        <TweakSection label="Affichage" />
        <TweakRadio label="Densité" value={t.density}
                    options={[{ value: "compact", label: "Compact" }, { value: "regular", label: "Standard" }, { value: "comfy", label: "Aéré" }]}
                    onChange={(v) => setTweak("density", v)} />
        <TweakRadio label="Vue calendrier" value={t.calView}
                    options={[{ value: "month", label: "Mois" }, { value: "week", label: "Semaine" }, { value: "list", label: "Liste" }]}
                    onChange={(v) => setTweak("calView", v)} />
        <TweakSection label="Ambiance" />
        <TweakColor label="Accent" value={t.accent} options={ACCENTS}
                    onChange={(v) => setTweak("accent", v)} />
        <TweakToggle label="Grain papier" value={t.grain} onChange={(v) => setTweak("grain", v)} />
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
