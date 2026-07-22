import { fireEvent, render, screen } from '@testing-library/react';
import { App } from './App';

describe('Wasplex UI catalogue', () => {
  it('identifies all content as synthetic demonstration data', () => {
    render(<App />);
    expect(screen.getByText(/Démonstration · données fictives/i)).toBeInTheDocument();
  });

  it('renders literal wallet states and never hides unknown behind success', () => {
    window.history.replaceState(null, '', '?story=foundations%2Fwallet&fixture=unknown-result&theme=dark&width=390');
    render(<App />);
    expect(screen.getByText("Le résultat n'est pas encore établi")).toBeInTheDocument();
    expect(screen.getByText(/50 WP restent réservés/)).toBeInTheDocument();
    expect(screen.getByText(/Ne relancez pas le retrait/)).toBeInTheDocument();
  });

  it('creates a stable URL when the viewport changes', () => {
    render(<App />);
    fireEvent.change(screen.getByLabelText('Largeur'), { target: { value: '320' } });
    expect(window.location.search).toContain('width=320');
  });
});
