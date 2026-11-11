import { A as e, C as t, F as n, Ot as r, St as i, bt as a, wt as o, xt as s } from "./lib-9QpRoHAM.js";
import { u as c } from "./lib-DaU_YkmU.js";
import { i as l, t as u } from "./host-L1lfHHpX.js";
import { i as d } from "./runtime-DBQRQGPT.js";
// src/Resources/assets/src/commands/markdown-clipboard.ts
var f = /* @__PURE__ */ new Set([
	"HTML",
	"HEAD",
	"BODY",
	"META",
	"TITLE",
	"STYLE",
	"DIV",
	"P",
	"BR",
	"PRE",
	"CODE",
	"SPAN"
]), p = [
	/^ {0,3}#{1,6}[ \t]+\S/m,
	/^ {0,3}(?:[-*+]|\d{1,9}[.)])[ \t]+\S/m,
	/\*\*[^*\n]+\*\*|__[^_\n]+__/,
	/\[[^\]\n]+\]\([^)\s]+\)/,
	/^ {0,3}(?:`{3,}|~{3,})/m,
	/^ {0,3}\|?(?:[ \t]*:?-{3,}:?[ \t]*\|)+/m,
	/^ {0,3}>[ \t]?\S/m
];
function m(e) {
	return [...new DOMParser().parseFromString(e, "text/html").querySelectorAll("*")].every((e) => f.has(e.tagName));
}
function h(e) {
	return p.some((t) => t.test(e));
}
function g(n) {
	return {
		parse: (e) => {
			try {
				let r = n.get(t)(e);
				return r instanceof o ? r : null;
			} catch {
				return null;
			}
		},
		serialize: (t) => n.get(e)(t),
		get hostSchemes() {
			return n.get(d.key).hostSchemes;
		}
	};
}
function _(e) {
	let t = document.createElement("div");
	return t.append(s.fromSchema(e.type.schema).serializeFragment(e.content, { document })), a.fromSchema(e.type.schema).parse(t);
}
var v = /* @__PURE__ */ new Set([
	"http",
	"https",
	"mailto",
	"tel"
]);
function y(e) {
	if (typeof e != "string") return !0;
	let t = e.replace(/[\u0000-\u0020]+/g, ""), n = /^([a-z][a-z0-9+.-]*):/i.exec(t)?.[1];
	return n === void 0 || v.has(n.toLowerCase());
}
var b = (e) => (t) => {
	if (y(t)) return !0;
	let n = typeof t == "string" ? /^[ \t\n\v\f\r]*([a-z][a-z0-9+.-]*):/i.exec(t)?.[1]?.toLowerCase() : void 0;
	return n !== void 0 && !u.has(n) && l(t, e);
};
function x(e, t = y) {
	let n = [], r = (e) => t(e.attrs.href) ? e : e.type.create({
		...e.attrs,
		href: ""
	});
	return e.forEach((e) => {
		let i = e.marks.map(r);
		if (e.isText) {
			n.push(e.mark(i));
			return;
		}
		let a = t(e.attrs.src) ? e.attrs : {
			...e.attrs,
			src: ""
		};
		n.push(e.type.create(a, x(e.content, t), i));
	}), i.from(n);
}
var S = (e, t = {}) => new r(x(e.content, b(t)), e.openStart, e.openEnd), C = (e) => e.selection.$from.parent.type.spec.code === !0;
function w(e, t, n) {
	if (n === "") return !1;
	if (C(e.state)) return e.dispatch(e.state.tr.insertText(n).scrollIntoView()), !0;
	let i = t.parse(n);
	if (!i || i.content.size === 0) return !1;
	let a = i.type.create(i.attrs, x(_(i).content, b(t.hostSchemes ?? {}))), o = a.childCount === 1 && a.firstChild.type.name === "paragraph";
	return e.dispatch(e.state.tr.replaceSelection(o ? r.maxOpen(a.content) : new r(a.content, 0, 0)).scrollIntoView()), !0;
}
function T(e, t) {
	let { from: n, to: r, empty: i } = e.selection;
	return i ? "" : t.serialize(e.doc.cut(n, r));
}
function E(e) {
	return (t, n) => {
		let r = n.clipboardData;
		if (!r || !t.editable || C(t.state)) return !1;
		let i = r.getData("text/html"), a = r.getData("text/plain");
		return i === "" ? a !== "" && w(t, e, a) : !h(a) || !m(i) ? !1 : w(t, e, a);
	};
}
async function D(e, t, n) {
	let r = await n.readText?.() ?? "", i = e.editable && w(e, t, r);
	return e.focus(), i;
}
async function O(e, t, n) {
	let r = T(e, t);
	if (r === "" || !n.writeText) return !1;
	try {
		return await n.writeText(r), !0;
	} catch {
		return !1;
	}
}
var k = () => typeof navigator < "u" && navigator.clipboard ? navigator.clipboard : {};
function A(e, t = k) {
	return c((r) => {
		let i = g(r), a = (n) => !n.selection.empty && (O(n, i, t()).then((t) => e(t ? "clipboard.copied_markdown" : "clipboard.copy_failed", !t)), !0);
		return n({
			"Mod-Alt-v": (e, n, r) => r?.editable ? (D(r, i, t()), !0) : !1,
			"Mod-Alt-Shift-C": a,
			"Mod-Alt-Shift-c": a
		});
	});
}
export { k as browserClipboard, x as cleanFragment, S as cleanPasted, O as copyMarkdown, w as insertMarkdown, y as isSafePastedUrl, m as isTextWrapper, h as looksLikeMarkdown, A as markdownClipboardPlugin, g as markdownIo, b as pasteCheckFor, D as pasteMarkdown, T as selectionMarkdown, E as smartPaste, _ as throughSchema };
