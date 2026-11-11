import { ut as e } from "./lib-9QpRoHAM.js";
// src/Resources/assets/src/commands/insert.ts
var t = [
	"h1",
	"h2",
	"h3",
	"bullet_list",
	"ordered_list",
	"task_list",
	"table",
	"code",
	"callout",
	"divider",
	"math",
	"mermaid"
];
function n(t) {
	let { $from: n } = t.state.selection;
	if (n.depth === 1 && n.parent.type.name === "paragraph" && n.parent.content.size === 0) return;
	let r = n.depth >= 1 ? n.after(1) : t.state.doc.content.size, i = t.state.tr.insert(r, t.state.schema.nodes.paragraph.create());
	t.dispatch(i.setSelection(e.create(i.doc, r + 1)).scrollIntoView());
}
function r(e, n) {
	let r = new Map(e.map((e) => [e.item.id, e])), i = [...n];
	for (let e of t) {
		let t = r.get(e);
		t && i.push({
			id: e,
			label: t.item.label,
			run: t.run
		});
	}
	return i;
}
// src/Resources/assets/src/context-menu-registry.ts
var i = /* @__PURE__ */ new Map();
function a(e) {
	return i.set(e.id, e), () => {
		i.get(e.id) === e && i.delete(e.id);
	};
}
export { n as i, a as n, r, i as t };
