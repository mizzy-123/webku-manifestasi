export type EditLink = {
    text?: string;
    href?: string;
};

export type ElementGroup = {
    texts: Record<string, string>;
    images: Record<string, string>;
    links: Record<string, EditLink>;
};

export type CustomData = {
    colors: Record<string, string>;
    global: ElementGroup;
    pages: Record<string, ElementGroup>;
};

export type EditorPage = {
    slug: string;
    title: string;
    file: string;
};

export type EditorColor = {
    variable: string;
    label: string;
    default: string;
};

export type EditorTemplate = {
    id: number;
    name: string;
    slug: string;
    type: 'single-page' | 'multi-page';
    pages: EditorPage[];
    colors: EditorColor[];
};

export type EditScope = 'global' | 'page';

export type Viewport = 'desktop' | 'tablet' | 'mobile';

export type ActiveElement =
    | { type: 'image'; id: string; scope: EditScope; src: string }
    | {
          type: 'link';
          id: string;
          scope: EditScope;
          text: string;
          href: string;
      };

/** Messages sent from the template iframe to the editor. */
export type BridgeMessage =
    | { type: 'IFRAME_READY' }
    | { type: 'TEXT_UPDATED'; id: string; scope: EditScope; value: string }
    | { type: 'IMAGE_CLICKED'; id: string; scope: EditScope; src: string }
    | {
          type: 'LINK_CLICKED';
          id: string;
          scope: EditScope;
          text: string;
          href: string;
      }
    | { type: 'NAVIGATE_PAGE'; file: string };
