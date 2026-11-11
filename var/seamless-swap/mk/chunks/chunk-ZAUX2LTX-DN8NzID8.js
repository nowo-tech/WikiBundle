import { s as e } from "./chunk-HAGFOTED-kvCvjHr_.js";
import { d as t } from "./chunk-7S6XSTFO-BYt5Vr8B.js";
import { m as n } from "./src-ckL58JCF.js";
import { n as r } from "./chunk-Y2CYZVJY-fpkXlLv-.js";
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/chunk-ZAUX2LTX.mjs
var i = /* @__PURE__ */ r(({ flowchart: e }) => {
	let t = e?.subGraphTitleMargin?.top ?? 0, n = e?.subGraphTitleMargin?.bottom ?? 0;
	return {
		subGraphTitleTopMargin: t,
		subGraphTitleBottomMargin: n,
		subGraphTitleTotalMargin: t + n
	};
}, "getSubGraphTitleMargins"), a = /* @__PURE__ */ new Map();
async function o(n, r, i) {
	let o, s;
	r.shape === "rect" && (r.shape = r.rx && r.ry ? "roundedRect" : "squareRect");
	let c = r.shape ? e[r.shape] : void 0;
	if (!c) throw Error(`No such shape: ${r.shape}. Please check your syntax.`);
	if (r.link) {
		let e;
		i.config.securityLevel === "sandbox" ? e = "_top" : r.linkTarget && (e = r.linkTarget || "_blank"), o = n.insert("svg:a").attr("xlink:href", r.link).attr("target", e ?? null), s = await c(o, r, i);
	} else s = await c(n, r, i), o = s;
	return o.attr("data-look", t(r.look)), r.tooltip && s.attr("title", r.tooltip), a.set(r.id, o), r.haveCallback && o.attr("class", o.attr("class") + " clickable"), o;
}
r(o, "insertNode");
var s = /* @__PURE__ */ r((e, t) => {
	a.set(t.id, e);
}, "setNodeElem"), c = /* @__PURE__ */ r(() => {
	a.clear();
}, "clear"), l = /* @__PURE__ */ r((e) => {
	let t = a.get(e.id);
	n.trace("Transforming node", e.diff, e, "translate(" + (e.x - e.width / 2 - 5) + ", " + e.width / 2 + ")");
	let r = e.diff || 0;
	return e.clusterNode ? t.attr("transform", "translate(" + (e.x + r - e.width / 2) + ", " + (e.y - e.height / 2 - 8) + ")") : t.attr("transform", "translate(" + e.x + ", " + e.y + ")"), r;
}, "positionNode");
export { s as a, l as i, i as n, o as r, c as t };
