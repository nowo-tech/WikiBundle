import { M as e, j as t, ot as n, st as r } from "./lib-9QpRoHAM.js";
import { u as i } from "./lib-DaU_YkmU.js";
import { r as a, t as o } from "./heading-slug-DVQeGYqO.js";
// src/Resources/assets/src/plugins/heading-ids.ts
function s(e) {
	let t = [];
	e.doc.descendants((e, n) => (e.type.name === "heading" && t.push({
		pos: n,
		text: e.textContent
	}), e.isBlock));
	let n = a(t.map((e) => e.text));
	return new Map(t.map((e, t) => [e.pos, o + n[t]]));
}
var c = new r("nowo-milkdown-heading-ids"), l = (n) => e.create(n.doc, [...s(n)].map(([e, r]) => t.node(e, e + n.doc.nodeAt(e).nodeSize, { id: r }))), u = i(() => new n({
	key: c,
	state: {
		init: (e, t) => l(t),
		apply: (e, t, n, r) => e.docChanged ? l(r) : t
	},
	props: { decorations: (e) => c.getState(e) }
}));
export { u as headingIdsPlugin };
