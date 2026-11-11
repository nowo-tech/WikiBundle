// src/Resources/assets/src/preview/mermaid.ts
var e = null, t = 0, n = Promise.resolve(), r = { load: () => import("./mermaid.core-BH4raDJh.js").then((e) => e.default) }, i = [], a = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
function o(e) {
	return e.replace(a, (e, t) => `<g data-nowo-style="${i.push(t) - 1}"></g>`);
}
async function s(e, t) {
	if (!e) return t();
	let n = Document.prototype.createElement, r = Document.prototype.createElementNS, i = DOMParser.prototype.parseFromString, a = (t) => (t.localName === "style" && t.setAttribute("nonce", e), t);
	Document.prototype.createElement = function(...e) {
		return a(n.apply(this, e));
	}, Document.prototype.createElementNS = function(...e) {
		return a(r.apply(this, e));
	}, DOMParser.prototype.parseFromString = function(e, t) {
		return i.call(this, o(String(e)), t);
	};
	try {
		return await t();
	} finally {
		Document.prototype.createElement = n, Document.prototype.createElementNS = r, DOMParser.prototype.parseFromString = i;
	}
}
function c(e, t, n, r = !1) {
	let a = new DOMParser().parseFromString(o(e), "image/svg+xml").documentElement;
	if (a.localName !== "svg") throw Error("mermaid: not an SVG");
	a.querySelectorAll("script, foreignObject").forEach((e) => e.remove()), [a, ...a.querySelectorAll("*")].forEach((e) => {
		for (let t of [...e.attributes]) /^on/i.test(t.name) && e.removeAttribute(t.name);
		r || x(e);
	});
	let s = document.importNode(a, !0);
	return s.querySelectorAll("g[data-nowo-style]").forEach((e) => {
		let n = i[Number(e.getAttribute("data-nowo-style"))];
		if (!t || n === void 0) {
			e.remove();
			return;
		}
		let a = document.createElementNS("http://www.w3.org/2000/svg", "style");
		a.setAttribute("nonce", t), a.textContent = r ? n : y(n), e.replaceWith(a);
	}), s.setAttribute("role", "img"), s.setAttribute("aria-label", n), s;
}
var l = /* @__PURE__ */ new Set([
	"image",
	"use",
	"feimage",
	"pattern"
]), u = /* @__PURE__ */ new Set(["set", "animate"]), d = [
	"secure",
	"securityLevel",
	"startOnLoad",
	"maxTextSize",
	"suppressErrorRendering",
	"maxEdges"
], f = [
	"themeCSS",
	"themeVariables",
	"fontFamily",
	"altFontFamily"
];
function p(e) {
	let t = "", n = 0;
	for (; n < e.length;) {
		let r = e.indexOf("@{", n);
		if (r < 0) break;
		let i = r + 2, a = "";
		for (; i < e.length; i++) {
			let t = e[i];
			if (a) t === "\\" ? i++ : t === a && (a = "");
			else if (t === "\"" || t === "'") a = t;
			else if (t === "}") break;
		}
		let o = e.slice(r, i + 1);
		t += e.slice(n, r) + (/img|\\/i.test(o) ? "" : o), n = i + 1;
	}
	return t + e.slice(n);
}
var m = /\b(?:style|classDef|linkStyle|cssClass)\b(?:\s+(?:"[^"]*"|\S+))?\s*/i, h = /url\(|image-set\(|@import|expression\(/i;
function g(e) {
	let t = [], n = 0, r = "", i = 0;
	for (let a = 0; a < e.length; a++) {
		let o = e[a];
		r ? o === r && (r = "") : o === "\"" || o === "'" ? r = o : o === "(" ? n++ : o === ")" ? n = Math.max(0, n - 1) : (o === "," || o === ";") && n === 0 && (t.push(e.slice(i, a + 1)), i = a + 1);
	}
	return t.push(e.slice(i)), t;
}
function _(e) {
	return e.split("\n").map((e) => {
		let t = v(e), n = m.exec(t);
		if (!n || !h.test(t)) return e;
		let r = t.slice(0, n.index + n[0].length);
		return (r + g(t.slice(r.length)).filter((e) => !h.test(e)).join("")).replace(/[\s,;]+$/, "");
	}).join("\n");
}
function v(e) {
	return e.replace(/\/\*[\s\S]*?(?:\*\/|$)/g, "").replace(/\\(?:([0-9a-f]{1,6})[ \t\n\r\f]?|(\r\n|[\n\r\f])|([\s\S]))/gi, (e, t, n, r) => {
		if (t !== void 0) {
			let e = parseInt(t, 16);
			return e > 0 && e <= 1114111 && (e < 55296 || e > 57343) ? String.fromCodePoint(e) : "�";
		}
		return r ?? "";
	});
}
function y(e) {
	let t = v(e).replace(/@import\b[^;]*;?/gi, "").replace(/@font-face\s*\{[^}]*\}?/gi, "").replace(/(?:-webkit-)?image-set\((?:[^()]|\([^()]*\))*\)/gi, "none").replace(/url\(\s*(?:"([^"]*)"|'([^']*)'|([^)]*?))\s*\)/gi, (e, t, n, r) => b(t ?? n ?? r ?? "") ? e : "none");
	for (let e of t.matchAll(/url\(\s*['"]?([^'")]*)/gi)) if (!b(e[1] ?? "")) return "";
	return /@import|image-set\(/i.test(t) ? "" : t;
}
function b(e) {
	let t = e.replace(/[\t\n\r]/g, "").replace(/^[\u0000-\u0020]+/, "");
	if (t === "" || t.startsWith("#") || /^data:image\/(?:png|gif|jpeg|webp)[;,]/i.test(t)) return !0;
	try {
		return new URL(t, document.baseURI).origin === new URL(document.baseURI).origin;
	} catch {
		return !1;
	}
}
function x(e) {
	let t = e.localName.toLowerCase();
	if (l.has(t)) for (let t of [...e.attributes]) t.localName === "href" && !b(t.value) && e.removeAttributeNode(t);
	if (u.has(t) && /href/i.test(e.getAttribute("attributeName") ?? "")) {
		e.remove();
		return;
	}
	let n = e.getAttribute("style");
	if (n !== null) {
		let t = y(n);
		t !== n && e.setAttribute("style", t);
	}
}
async function S(a, o, l, u, m = !1) {
	let h = await (e ??= r.load());
	h.initialize({
		startOnLoad: !1,
		securityLevel: "strict",
		htmlLabels: !1,
		flowchart: { htmlLabels: !1 },
		theme: l ? "dark" : "neutral",
		fontFamily: "inherit",
		secure: m ? d : [...d, ...f]
	});
	let g = m ? a : _(p(a)), v = n.then(async () => {
		try {
			let { svg: e } = await s(o, () => h.render(`nowo-mermaid-${++t}`, g));
			return c(e, o, u, m);
		} finally {
			i.length = 0;
		}
	});
	return n = v.then(() => void 0, () => void 0), v;
}
export { S as t };
