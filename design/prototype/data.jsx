/* ============================================================
   Données mockées — Les Mélèzes
   Tout est local : aucune dépendance à un service tiers.
   ============================================================ */

// — Foyers / occupants (petit cercle familial) —
const OCCUPANTS = [
  { id: "cm",  name: "Claire & Marc", short: "C&M", role: "Parents",       color: "var(--p1)", bg: "var(--p1-bg)", pk: "p1" },
  { id: "lea", name: "Léa",           short: "LÉ",  role: "Fille",         color: "var(--p2)", bg: "var(--p2-bg)", pk: "p2" },
  { id: "tom", name: "Tom",           short: "TO",  role: "Fils",          color: "var(--p3)", bg: "var(--p3-bg)", pk: "p3" },
  { id: "gp",  name: "Grands-parents",short: "GP",  role: "Jeanne & Henri",color: "var(--p4)", bg: "var(--p4-bg)", pk: "p4" },
  { id: "amis",name: "Amis / prêt",   short: "AM",  role: "Invités",       color: "var(--p5)", bg: "var(--p5-bg)", pk: "p5" },
];
const occ = (id) => OCCUPANTS.find((o) => o.id === id);

// — Réservations / occupation (ISO yyyy-mm-dd, fin exclusive) —
// Référence : nous sommes le 2 juin 2026.
const RESERVATIONS = [
  { id: "r1",  occ: "cm",   start: "2026-05-29", end: "2026-06-02", note: "Week-end prolongé · ouverture saison", guests: 2 },
  { id: "r2",  occ: "lea",  start: "2026-06-05", end: "2026-06-08", note: "Léa + 3 amis · rando Gerbier",        guests: 4 },
  { id: "r3",  occ: "gp",   start: "2026-06-11", end: "2026-06-15", note: "Jeanne & Henri · cure d'air",          guests: 2 },
  { id: "r4",  occ: "cm",   start: "2026-06-20", end: "2026-06-28", note: "Famille complète · semaine d'été",     guests: 5 },
  { id: "r5",  occ: "amis", start: "2026-07-03", end: "2026-07-06", note: "Prêt aux Vasseur · 14 juillet",        guests: 4 },
  { id: "r6",  occ: "tom",  start: "2026-07-10", end: "2026-07-19", note: "Tom · stage escalade",                 guests: 1 },
  { id: "r7",  occ: "cm",   start: "2026-08-01", end: "2026-08-16", note: "Grandes vacances",                     guests: 5 },
  { id: "r8",  occ: "gp",   start: "2026-05-18", end: "2026-05-22", note: "Jeanne & Henri · printemps",           guests: 2 },
  { id: "r9",  occ: "lea",  start: "2026-05-08", end: "2026-05-11", note: "Léa · révisions au calme",             guests: 1 },
];

// — Inventaire —
const INV_CATS = [
  { id: "linge",  label: "Linge",        ico: "linen" },
  { id: "cuisine",label: "Vaisselle & cuisine", ico: "dish" },
  { id: "equip",  label: "Équipement",   ico: "gear" },
  { id: "bain",   label: "Salle de bain",ico: "bath" },
];

// state: ok | worn | replace
const INVENTORY = [
  // Linge
  { id:"i01", cat:"linge",  name:"Housses de couette 240×220", qty:4, unit:"", loc:"Placard couloir", state:"ok",   note:"2 motifs sapin, 2 unies" },
  { id:"i02", cat:"linge",  name:"Draps housse 160",          qty:5, unit:"", loc:"Placard couloir", state:"ok",   note:"" },
  { id:"i03", cat:"linge",  name:"Taies d'oreiller",          qty:10,unit:"", loc:"Placard couloir", state:"worn", note:"3 à recoudre" },
  { id:"i04", cat:"linge",  name:"Serviettes de bain",        qty:8, unit:"", loc:"Placard SdB",     state:"ok",   note:"" },
  { id:"i05", cat:"linge",  name:"Plaids laine",              qty:3, unit:"", loc:"Salon",           state:"ok",   note:"" },
  { id:"i06", cat:"linge",  name:"Couvertures d'appoint",     qty:2, unit:"", loc:"Cabane skis",     state:"replace",note:"Mitées — à remplacer" },
  // Vaisselle & cuisine
  { id:"i07", cat:"cuisine",name:"Assiettes plates",          qty:12,unit:"", loc:"Buffet",          state:"ok",   note:"" },
  { id:"i08", cat:"cuisine",name:"Assiettes creuses",         qty:9, unit:"", loc:"Buffet",          state:"worn", note:"1 ébréchée" },
  { id:"i09", cat:"cuisine",name:"Verres à eau",              qty:8, unit:"", loc:"Buffet",          state:"replace",note:"6 dépareillés, 2 cassés cet hiver" },
  { id:"i10", cat:"cuisine",name:"Couverts (sets)",           qty:12,unit:"", loc:"Tiroir",          state:"ok",   note:"" },
  { id:"i11", cat:"cuisine",name:"Casseroles",                qty:4, unit:"", loc:"Sous l'évier",    state:"ok",   note:"" },
  { id:"i12", cat:"cuisine",name:"Cocotte fonte",             qty:1, unit:"", loc:"Sous l'évier",    state:"ok",   note:"Le Creuset hérité" },
  { id:"i13", cat:"cuisine",name:"Tasses / mugs",             qty:7, unit:"", loc:"Buffet",          state:"worn", note:"" },
  // Équipement
  { id:"i14", cat:"equip",  name:"Raclette électrique",       qty:1, unit:"", loc:"Cabane skis",     state:"ok",   note:"8 personnes" },
  { id:"i15", cat:"equip",  name:"Fer + table à repasser",    qty:1, unit:"", loc:"Buanderie",       state:"ok",   note:"" },
  { id:"i16", cat:"equip",  name:"Aspirateur",                qty:1, unit:"", loc:"Placard entrée",  state:"worn", note:"Brosse fatiguée" },
  { id:"i17", cat:"equip",  name:"Luge / ESF débutant",       qty:3, unit:"", loc:"Cabane skis",     state:"ok",   note:"" },
  { id:"i18", cat:"equip",  name:"Sèche-cheveux",             qty:2, unit:"", loc:"SdB",             state:"replace",note:"1 HS" },
  { id:"i19", cat:"equip",  name:"Kit premiers secours",      qty:1, unit:"", loc:"Entrée",          state:"worn", note:"À recompléter" },
  // Salle de bain
  { id:"i20", cat:"bain",   name:"Tapis de bain",             qty:3, unit:"", loc:"SdB",             state:"ok",   note:"" },
  { id:"i21", cat:"bain",   name:"Balai WC + brosse",         qty:2, unit:"", loc:"WC",              state:"ok",   note:"" },
];

// — Courses récurrentes / consommables —
// stock: niveau actuel (0..max). Si stock <= seuil → à racheter.
const COURSES = [
  { id:"c01", name:"Café moulu",            cat:"Épicerie",   stock:1, max:4, seuil:1, unit:"paquet",  recur:"À chaque séjour", flag:true },
  { id:"c02", name:"Pastilles lave-vaisselle",cat:"Entretien",stock:8, max:40,seuil:10,unit:"past.",   recur:"Mensuel",         flag:true },
  { id:"c03", name:"Papier toilette",        cat:"Entretien",  stock:4, max:24,seuil:6, unit:"rouleau", recur:"À chaque séjour", flag:true },
  { id:"c04", name:"Sel & poivre",           cat:"Épicerie",   stock:2, max:2, seuil:1, unit:"",        recur:"Trimestriel",     flag:false },
  { id:"c05", name:"Huile d'olive",          cat:"Épicerie",   stock:1, max:2, seuil:1, unit:"L",       recur:"Bimestriel",      flag:true },
  { id:"c06", name:"Sacs poubelle 30L",      cat:"Entretien",  stock:12,max:30,seuil:8, unit:"sac",     recur:"Mensuel",         flag:false },
  { id:"c07", name:"Liquide vaisselle",      cat:"Entretien",  stock:1, max:3, seuil:1, unit:"flacon",  recur:"Mensuel",         flag:false },
  { id:"c08", name:"Bois de chauffage",      cat:"Chauffage",  stock:0, max:6, seuil:2, unit:"stère",   recur:"Saison hiver",    flag:true },
  { id:"c09", name:"Allume-feu",             cat:"Chauffage",  stock:1, max:4, seuil:1, unit:"boîte",   recur:"Saison hiver",    flag:true },
  { id:"c10", name:"Pâtes / riz (stock sec)",cat:"Épicerie",   stock:3, max:6, seuil:2, unit:"paquet",  recur:"À chaque séjour", flag:false },
  { id:"c11", name:"Éponges",                cat:"Entretien",  stock:2, max:8, seuil:3, unit:"",        recur:"Mensuel",         flag:true },
  { id:"c12", name:"Tisanes / thé",          cat:"Épicerie",   stock:5, max:6, seuil:2, unit:"boîte",   recur:"Trimestriel",     flag:false },
];

Object.assign(window, { OCCUPANTS, occ, RESERVATIONS, INV_CATS, INVENTORY, COURSES });
