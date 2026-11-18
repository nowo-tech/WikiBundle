// Vite's `?raw` imports (the CSS token test reads wiki.css and docs/USAGE.md as text).
declare module '*?raw' {
  const content: string;
  export default content;
}
