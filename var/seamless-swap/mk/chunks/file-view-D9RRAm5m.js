import { at as e } from "./lib-9QpRoHAM.js";
import { d as t, u as n } from "./directives-Cjq-r_Gz.js";
import { d as r, h as i, m as a, u as o } from "./keyboard-DXH1K7BX.js";
import { a as s } from "./runtime-DBQRQGPT.js";
// src/Resources/assets/src/commands/file-view.ts
function c(t) {
	let { selection: n } = t, r = (e) => e?.type.name === "leafDirective" && e.attrs.name === "file";
	if (n instanceof e && r(n.node)) return {
		pos: n.from,
		node: n.node
	};
	let i = n.$from;
	return i.depth >= 1 && r(i.node(1)) ? {
		pos: i.before(1),
		node: i.node(1)
	} : null;
}
function l(e) {
	return (r, i) => {
		let a = c(r);
		if (!a) return !1;
		let o = n(a.node.attrs.attributes);
		for (let [t, n] of Object.entries(e)) n === null ? delete o[t] : o[t] = n;
		return i?.(r.tr.setNodeMarkup(a.pos, void 0, {
			...a.node.attrs,
			attributes: t(o)
		})), !0;
	};
}
var u = (e) => l(e === "card" ? {
	view: null,
	height: null
} : {
	view: e,
	...e === "preview" ? {} : { height: null }
});
function d(e, t, d) {
	let f = c(e);
	if (!f) return [];
	let p = n(f.node.attrs.attributes), m = a(p), h = [{
		id: "file_view",
		label: s(t, "menu.file_view"),
		items: o.map((e) => ({
			id: `file_view_${e}`,
			label: s(t, `file.view.${e}`),
			checked: e === m,
			run: () => d(u(e))
		}))
	}];
	if (m === "preview") {
		let e = i(p.height);
		h.push({
			id: "file_height",
			label: s(t, "menu.file_height"),
			items: Object.entries(r.presets).map(([n, r]) => ({
				id: `file_height_${n}`,
				label: s(t, `file.height.${n}`),
				checked: e === r,
				run: () => d(l({ height: String(r) }))
			}))
		});
	}
	return h;
}
export { d as t };
