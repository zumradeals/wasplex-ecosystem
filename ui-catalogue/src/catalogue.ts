export type Theme = 'light' | 'dark';
export type Viewport = 320 | 360 | 390 | 768 | 1024 | 1440;
export type FixtureId = 'default' | 'loading' | 'empty' | 'offline' | 'unknown-result' | 'confirmed';
export type StoryId = 'foundations/status' | 'foundations/wallet' | 'foundations/system-states';

export type Story = {
  id: StoryId;
  label: string;
  component: string;
  risk: 'Q0' | 'Q1' | 'Q2' | 'Q3';
  contract: string;
  fixtures: FixtureId[];
};

export const stories: Story[] = [
  {
    id: 'foundations/status',
    label: 'Statuts littéraux',
    component: 'CMP-013',
    risk: 'Q0',
    contract: 'DS-0001 · FND-00-07',
    fixtures: ['default', 'loading', 'confirmed', 'unknown-result'],
  },
  {
    id: 'foundations/wallet',
    label: 'Montants et vérité Wallet',
    component: 'CMP-014 · CMP-015 · CMP-019',
    risk: 'Q0',
    contract: 'AMD-0011 · ADR-0003 · FND-00-11',
    fixtures: ['default', 'loading', 'empty', 'offline', 'unknown-result', 'confirmed'],
  },
  {
    id: 'foundations/system-states',
    label: 'États système',
    component: 'CMP-018 · CMP-020 · CMP-021 · CMP-022',
    risk: 'Q1',
    contract: 'UX-0001 · UX-0002 · FND-00-07',
    fixtures: ['loading', 'empty', 'offline', 'unknown-result'],
  },
];

export const viewportOptions: Viewport[] = [320, 360, 390, 768, 1024, 1440];
