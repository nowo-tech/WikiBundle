import { t as e } from "./array-CR4KRclK.js";
import { n as t, t as n } from "./path-ezscG-65.js";
import { at as r } from "./chunk-7S6XSTFO-BYt5Vr8B.js";
// node_modules/.pnpm/d3-shape@3.2.0/node_modules/d3-shape/src/point.js
function i(e) {
	return e[0];
}
function a(e) {
	return e[1];
}
// node_modules/.pnpm/d3-shape@3.2.0/node_modules/d3-shape/src/line.js
function o(o, s) {
	var c = t(!0), l = null, u = r, d = null, f = n(p);
	o = typeof o == "function" ? o : o === void 0 ? i : t(o), s = typeof s == "function" ? s : s === void 0 ? a : t(s);
	function p(t) {
		var n, r = (t = e(t)).length, i, a = !1, p;
		for (l ?? (d = u(p = f())), n = 0; n <= r; ++n) !(n < r && c(i = t[n], n, t)) === a && ((a = !a) ? d.lineStart() : d.lineEnd()), a && d.point(+o(i, n, t), +s(i, n, t));
		if (p) return d = null, p + "" || null;
	}
	return p.x = function(e) {
		return arguments.length ? (o = typeof e == "function" ? e : t(+e), p) : o;
	}, p.y = function(e) {
		return arguments.length ? (s = typeof e == "function" ? e : t(+e), p) : s;
	}, p.defined = function(e) {
		return arguments.length ? (c = typeof e == "function" ? e : t(!!e), p) : c;
	}, p.curve = function(e) {
		return arguments.length ? (u = e, l != null && (d = u(l)), p) : u;
	}, p.context = function(e) {
		return arguments.length ? (e == null ? l = d = null : d = u(l = e), p) : l;
	}, p;
}
export { o as t };
