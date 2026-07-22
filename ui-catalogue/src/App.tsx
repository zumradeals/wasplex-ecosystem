import { useEffect, useMemo, useState } from 'react';
import { stories, viewportOptions, type FixtureId, type StoryId, type Theme, type Viewport } from './catalogue';
import { StoryCanvas } from './components/StoryCanvas';

function readInitialState() {
  const params = new URLSearchParams(window.location.search);
  const story = stories.find((item) => item.id === params.get('story')) ?? stories[1];
  const requestedFixture = params.get('fixture') as FixtureId | null;
  const fixture = requestedFixture && story.fixtures.includes(requestedFixture) ? requestedFixture : story.fixtures[0];
  const width = Number(params.get('width')) as Viewport;
  return {
    storyId: story.id,
    fixture,
    theme: params.get('theme') === 'light' ? 'light' as Theme : 'dark' as Theme,
    viewport: viewportOptions.includes(width) ? width : 390 as Viewport,
  };
}

export function App() {
  const initial = useMemo(readInitialState, []);
  const [storyId, setStoryId] = useState<StoryId>(initial.storyId);
  const [fixture, setFixture] = useState<FixtureId>(initial.fixture);
  const [theme, setTheme] = useState<Theme>(initial.theme);
  const [viewport, setViewport] = useState<Viewport>(initial.viewport);
  const [navOpen, setNavOpen] = useState(false);
  const story = stories.find((item) => item.id === storyId) ?? stories[0];

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    const params = new URLSearchParams({ story: storyId, fixture, theme, width: String(viewport) });
    window.history.replaceState(null, '', `?${params.toString()}`);
  }, [storyId, fixture, theme, viewport]);

  function selectStory(next: StoryId) {
    const selected = stories.find((item) => item.id === next)!;
    setStoryId(next);
    setFixture(selected.fixtures[0]);
    setNavOpen(false);
  }

  return (
    <div className="app-shell">
      <a className="skip-link" href="#story-render">Aller au prototype</a>
      <header className="topbar">
        <button className="icon-button menu-button" aria-label="Ouvrir le catalogue" aria-expanded={navOpen} onClick={() => setNavOpen(!navOpen)}>☰</button>
        <a className="brand" href="/" aria-label="Wasplex Catalogue, accueil">
          <span className="brand__mark" aria-hidden="true">W</span>
          <span><strong>Wasplex</strong><small>Catalogue UI · L00-A</small></span>
        </a>
        <span className="demo-flag"><span aria-hidden="true">●</span> Démonstration · données fictives</span>
      </header>

      <aside className={`sidebar ${navOpen ? 'sidebar--open' : ''}`} aria-label="Catalogue">
        <div className="sidebar__section"><span className="sidebar__label">Fondations</span>
          {stories.map((item) => (
            <button key={item.id} className={`story-link ${item.id === storyId ? 'story-link--active' : ''}`} onClick={() => selectStory(item.id)}>
              <span>{item.label}<small>{item.component}</small></span><span className={`risk risk--${item.risk.toLowerCase()}`}>{item.risk}</span>
            </button>
          ))}
        </div>
        <div className="sidebar__section sidebar__section--muted"><span className="sidebar__label">Prochaines tranches</span><p>Formulaires</p><p>Navigation</p><p>Preuves</p><p>Urgences</p></div>
        <footer className="sidebar__footer">DS-0001 · UX-0002 · UX-0003<br />Aucun backend connecté</footer>
      </aside>

      <main className="workspace">
        <section className="story-header" aria-labelledby="story-name">
          <div><span className="breadcrumb">Fondations / {story.component}</span><h1 id="story-name">{story.label}</h1><p>{story.contract}</p></div>
          <div className="trace"><span>État</span><strong>Prototype normalisé</strong><span>Risque</span><strong>{story.risk}</strong></div>
        </section>

        <section className="toolbar" aria-label="Paramètres du prototype">
          <label>Fixture<select value={fixture} onChange={(event) => setFixture(event.target.value as FixtureId)}>{story.fixtures.map((item) => <option key={item} value={item}>{item}</option>)}</select></label>
          <label>Largeur<select value={viewport} onChange={(event) => setViewport(Number(event.target.value) as Viewport)}>{viewportOptions.map((item) => <option key={item} value={item}>{item} px</option>)}</select></label>
          <label>Thème<select value={theme} onChange={(event) => setTheme(event.target.value as Theme)}><option value="dark">Sombre</option><option value="light">Clair</option></select></label>
          <label>Langue<select defaultValue="fr"><option value="fr">Français</option><option value="fr-long">Français · texte long</option></select></label>
        </section>

        <section className="canvas-area" aria-label={`Aperçu à ${viewport} pixels`}>
          <div className="viewport-ruler"><span>{viewport}px</span><span>{theme} · {fixture}</span></div>
          <div id="story-render" className="story-frame" style={{ width: `min(100%, ${viewport}px)` }} tabIndex={-1}>
            <StoryCanvas story={story} fixture={fixture} />
          </div>
        </section>
      </main>
      {navOpen && <button className="backdrop" aria-label="Fermer le catalogue" onClick={() => setNavOpen(false)} />}
    </div>
  );
}
