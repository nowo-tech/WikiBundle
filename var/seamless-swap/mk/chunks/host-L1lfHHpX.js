import { n as e } from "./link-titles-Cs1oogn7.js";
// src/Resources/assets/src/host.ts
var t = /^[ \t\n\v\f\r]*([a-z][a-z0-9+.-]{0,30}):/i, n = /^[ \t\n\v\f\r]*([a-z][a-z0-9+.-]{0,30}):([A-Za-z0-9_~-][A-Za-z0-9._~-]{0,199})[ \t\n\v\f\r]*$/i, r = /* @__PURE__ */ new Set([
	"http",
	"https",
	"javascript",
	"vbscript",
	"data",
	"file",
	"blob",
	"about",
	"mailto",
	"tel",
	"ftp",
	"ws",
	"wss",
	"view-source",
	"filesystem",
	"jar"
]), i = (e) => typeof e == "string" && e.length <= 500 && /^(?:\/(?![/\\])|https?:\/\/[^/?#{}\\]+\/).*\{id\}/is.test(e), a = /* @__PURE__ */ new WeakMap(), o = 300, s = 2048, c = /^[A-Za-z0-9_~-][A-Za-z0-9._~-]{0,199}$/;
function l(e) {
	if (typeof e != "string" || e.length > o || !/^[\x20-\x7e]+$/.test(e) || e.includes("#")) return !1;
	for (let t of e.matchAll(/\\([\s\S]?)/g)) if (t[1] === "" || !"dDwWsS./-_~+?*()[]{}|^$\\".includes(t[1])) return !1;
	let t = e.replace(/\\[\s\S]/g, "");
	if (!t.startsWith("^") || !t.endsWith("$") || t.split("(?<id>").length !== 2 || /\(\?(?!:|<id>)/.test(t) || [
		"(*",
		"[:",
		"[]",
		"[^]"
	].some((e) => t.includes(e)) || u(t)) return !1;
	try {
		return new RegExp(e), !0;
	} catch {
		return !1;
	}
}
function u(e) {
	let t = 0, n = !1;
	for (let r = 0; r < e.length; r++) {
		let i = e[r];
		if (n) n = i !== "]";
		else if (i === "[") n = !0;
		else if (i === "(") t++;
		else if (i === ")") t--;
		else if (i === "|" && t === 0) return !0;
	}
	return !1;
}
function d(e) {
	let t = "";
	for (let n = 1; n < e.length; n++) {
		let r = e[n];
		if (r === "\\" && n + 1 < e.length && "./-_~".includes(e[n + 1])) r = e[++n];
		else if (!/[A-Za-z0-9/:_~-]/.test(r)) break;
		let i = e[n + 1] ?? "";
		if (i !== "" && "?*{".includes(i) || (t += r, i === "+")) break;
	}
	return t;
}
function f(e, t = []) {
	let n = e.trim();
	n = n.slice(0, n.search(/[?#]/) === -1 ? n.length : n.search(/[?#]/));
	let r = /^(?:https?:)?\/\/([^/\\]*)(.*)$/is.exec(n);
	if (r) {
		let e = r[1].toLowerCase(), i = typeof location < "u" ? location.host.toLowerCase() : "";
		e !== "" && !e.includes("@") && (t.includes(e) || e === i) && (n = r[2] || "/");
	}
	try {
		n = decodeURIComponent(n);
	} catch {}
	return n.replace(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/gi, (e) => e.toLowerCase());
}
function p(e, t, n = []) {
	let r = [];
	for (let n of Array.isArray(t) ? t : []) {
		let t = n && typeof n == "object" ? n : {}, i = typeof t.scheme == "string" ? t.scheme.toLowerCase() : "";
		l(t.pattern) && Object.prototype.hasOwnProperty.call(e, i) && r.push({
			pattern: new RegExp(t.pattern),
			scheme: i,
			url: t.url === "resolved" ? "resolved" : "written"
		});
	}
	if (!r.length) return e;
	let i = { ...e }, o = Array.isArray(n) ? n.filter((e) => typeof e == "string").map((e) => e.toLowerCase()) : [];
	return a.set(i, {
		patterns: r,
		hosts: o
	}), i;
}
function m(e, t) {
	let n = a.get(t);
	if (!n || typeof e != "string") return null;
	let r = f(e, n.hosts);
	if (r === "") return null;
	for (let e of n.patterns) {
		if (r.length > s) {
			if (r.startsWith(d(e.pattern.source))) return {
				reference: null,
				url: e.url
			};
			continue;
		}
		let t = e.pattern.exec(r);
		if (!t) continue;
		let n = t.groups?.id ?? "";
		return {
			reference: c.test(n) ? `${e.scheme}:${n}` : null,
			url: e.url
		};
	}
	return null;
}
function h(e) {
	let t = {};
	if (!e || typeof e != "object" || Array.isArray(e)) return t;
	for (let [n, a] of Object.entries(e)) {
		if (!/^[a-z][a-z0-9+.-]{0,30}$/.test(n) || r.has(n) || !a || typeof a != "object") continue;
		let e = a;
		t[n] = {
			image: i(e.image) ? e.image : null,
			file: i(e.file) ? e.file : null,
			preview: i(e.preview) ? e.preview : null
		};
	}
	return t;
}
function g(e, n) {
	if (typeof e != "string") return !1;
	let r = t.exec(e);
	return !!r && Object.prototype.hasOwnProperty.call(n, r[1].toLowerCase());
}
function _(e, t) {
	if (typeof e != "string") return null;
	let r = n.exec(e);
	if (!r) return null;
	let i = r[1].toLowerCase();
	return Object.prototype.hasOwnProperty.call(t, i) ? `${i}:${r[2]}` : null;
}
function v(t, n, r) {
	let i;
	if (g(t, r)) i = _(t, r);
	else {
		let e = m(t, r);
		if (!e || e.url === "written") return;
		i = e.reference;
	}
	if (!i) return null;
	let [a, o] = i.split(/:(.*)/s), s = r[a], c = s[n] ?? (n === "image" ? s.file : null);
	return c && e(c.split("{id}").join(encodeURIComponent(o))) || null;
}
function y(t, n, r) {
	let i = v(t, n, r);
	return i === void 0 ? e(t) : i ?? "";
}
export { h as a, g as i, _ as n, y as o, v as r, p as s, r as t };
