/* ============================================================
   Icônes linéaires — Les Mélèzes
   Jeu de pictos cohérent (stroke 1.6, 24px viewBox).
   ============================================================ */

const _P = { fill: "none", stroke: "currentColor", strokeWidth: 1.6, strokeLinecap: "round", strokeLinejoin: "round" };

const ICONS = {
  calendar: <><rect x="3.5" y="4.5" width="17" height="16" rx="2.5" {..._P}/><path d="M3.5 9h17M8 2.5v4M16 2.5v4" {..._P}/></>,
  box: <><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z" {..._P}/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" {..._P}/></>,
  cart: <><path d="M3 4h2l2.2 11.2a1.4 1.4 0 0 0 1.4 1.1h8.2a1.4 1.4 0 0 0 1.4-1.1L21 8H6" {..._P}/><circle cx="9.5" cy="20" r="1.2" {..._P}/><circle cx="18" cy="20" r="1.2" {..._P}/></>,
  server: <><rect x="3.5" y="4" width="17" height="7" rx="2" {..._P}/><rect x="3.5" y="13" width="17" height="7" rx="2" {..._P}/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" {..._P}/></>,
  leaf: <><path d="M5 19c0-8 5-14 14-14 0 9-5 14-14 14z" {..._P}/><path d="M5 19c4-6 8-8 12-9" {..._P}/></>,
  plus: <path d="M12 5v14M5 12h14" {..._P}/>,
  chevL: <path d="M14.5 6 9 12l5.5 6" {..._P}/>,
  chevR: <path d="M9.5 6 15 12l-5.5 6" {..._P}/>,
  chevD: <path d="M6 9.5 12 15l6-5.5" {..._P}/>,
  search: <><circle cx="11" cy="11" r="6.5" {..._P}/><path d="m20 20-3.5-3.5" {..._P}/></>,
  check: <path d="M5 12.5 10 17.5 19.5 7" {..._P}/>,
  x: <path d="M6 6 18 18M18 6 6 18" {..._P}/>,
  edit: <><path d="M5 19h3l9.5-9.5a2 2 0 0 0-3-3L5 16z" {..._P}/><path d="M14 7l3 3" {..._P}/></>,
  trash: <><path d="M5 7h14M9 7V5h6v2M7 7l1 12.5a1.5 1.5 0 0 0 1.5 1.4h5a1.5 1.5 0 0 0 1.5-1.4L17 7" {..._P}/></>,
  grid: <><rect x="4" y="4" width="7" height="7" rx="1.5" {..._P}/><rect x="13" y="4" width="7" height="7" rx="1.5" {..._P}/><rect x="4" y="13" width="7" height="7" rx="1.5" {..._P}/><rect x="13" y="13" width="7" height="7" rx="1.5" {..._P}/></>,
  cols: <><rect x="4" y="4" width="16" height="16" rx="2" {..._P}/><path d="M9.3 4v16M14.6 4v16" {..._P}/></>,
  list: <path d="M4 6h16M4 12h16M4 18h16" {..._P}/>,
  user: <><circle cx="12" cy="8.5" r="3.5" {..._P}/><path d="M5.5 19a6.5 6.5 0 0 1 13 0" {..._P}/></>,
  users: <><circle cx="9" cy="9" r="3" {..._P}/><path d="M3.5 18a5.5 5.5 0 0 1 11 0M15 6.5a3 3 0 0 1 0 5.5M16 18a5.5 5.5 0 0 1 4.5-2" {..._P}/></>,
  alert: <><path d="M12 4 21 19.5H3z" {..._P}/><path d="M12 10v4M12 17h.01" {..._P}/></>,
  refresh: <><path d="M4 12a8 8 0 0 1 13.5-5.8L20 8M20 4v4h-4" {..._P}/><path d="M20 12a8 8 0 0 1-13.5 5.8L4 16M4 20v-4h4" {..._P}/></>,
  moon: <path d="M20 13.5A8 8 0 1 1 10.5 4 6.5 6.5 0 0 0 20 13.5z" {..._P}/>,
  pin: <><path d="M12 21s6.5-6 6.5-10.5a6.5 6.5 0 0 0-13 0C5.5 15 12 21 12 21z" {..._P}/><circle cx="12" cy="10.5" r="2.2" {..._P}/></>,
  clock: <><circle cx="12" cy="12" r="8" {..._P}/><path d="M12 7.5V12l3 1.8" {..._P}/></>,
  linen: <><path d="M4 8.5 8 5h8l4 3.5-2 2V19a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-8.5z" {..._P}/><path d="M9 5c0 1.8 1.3 3 3 3s3-1.2 3-3" {..._P}/></>,
  dish: <><circle cx="12" cy="12" r="8" {..._P}/><circle cx="12" cy="12" r="3" {..._P}/></>,
  gear: <><circle cx="12" cy="12" r="3" {..._P}/><path d="M12 3v2.5M12 18.5V21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M3 12h2.5M18.5 12H21M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8" {..._P}/></>,
  bath: <><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z" {..._P}/><path d="M6 12V6.5A2 2 0 0 1 8 4.5a2 2 0 0 1 2 2" {..._P}/><path d="M6.5 19l-1 2M17.5 19l1 2" {..._P}/></>,
  download: <><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14" {..._P}/></>,
  lock: <><rect x="5" y="11" width="14" height="9" rx="2" {..._P}/><path d="M8 11V8a4 4 0 0 1 8 0v3" {..._P}/></>,
  arrow: <path d="M5 12h14M13 6l6 6-6 6" {..._P}/>,
  sparkle: <path d="M12 4l1.8 5.2L19 11l-5.2 1.8L12 18l-1.8-5.2L5 11l5.2-1.8z" {..._P}/>,
  sun: <><circle cx="12" cy="12" r="4" {..._P}/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8" {..._P}/></>,
  bed: <><path d="M3 9v10M3 12h18v7M21 19v-2M21 12V9a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v3" {..._P}/><circle cx="7.5" cy="12" r="0" {..._P}/></>,
};

function Icon({ name, size = 18, className = "", style }) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} className={className} style={style} aria-hidden="true">
      {ICONS[name] || null}
    </svg>
  );
}

Object.assign(window, { Icon, ICONS });
