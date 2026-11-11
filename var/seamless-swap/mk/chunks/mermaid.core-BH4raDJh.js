import { $ as e, I as t, J as n, K as r, L as i, N as a, P as o, Q as s, S as c, T as l, V as u, W as d, Z as f, b as p, c as m, f as h, g, j as _, l as v, m as y, n as b, p as x, r as S, s as C, t as w, u as T, w as E, x as D, y as O } from "./chunk-DSNLSHJZ-DRpVu4wY.js";
import { n as k } from "./chunk-Y2CYZVJY-fpkXlLv-.js";
import { h as A, m as j, p as M } from "./src-ckL58JCF.js";
import { t as N } from "./chunk-DUW6YSOI-BTC2mVm4.js";
import { r as ee } from "./chunk-HNE4Q36P-CL4u6SQH.js";
import { a as P, c as F, i as I, l as L } from "./chunk-WQQL4CDK-DnC3N1CF.js";
import { r as R } from "./chunk-ZAUX2LTX-DN8NzID8.js";
import { o as z } from "./chunk-HAGFOTED-kvCvjHr_.js";
import { _ as B, a as te, b as ne, f as re, g as ie, i as ae, l as oe, o as se, p as ce, q as le, x as ue, y as de } from "./chunk-7S6XSTFO-BYt5Vr8B.js";
import "./chunk-UHW7PFBP-BFVgUIVD.js";
import { a as V } from "./chunk-AQDTSKZB-DonNB1xV.js";
import { r as fe } from "./chunk-YZO67HFG-CrOEWOHR.js";
import { t as pe } from "./purify.es-CZ6K1J3y.js";
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/chunk-OYAV6JZA.mjs
var me = /* @__PURE__ */ k((e) => {
	let { securityLevel: t } = p(), n = M("body");
	if (t === "sandbox") {
		let t = M(`#i${e}`).node()?.contentDocument ?? document;
		n = M(t.body);
	}
	return n.select(`#${e}`);
}, "selectSvgElement");
// node_modules/.pnpm/es-toolkit@1.53.1/node_modules/es-toolkit/dist/compat/_internal/isPrototype.mjs
function he(e) {
	let t = e?.constructor;
	return e === (typeof t == "function" ? t.prototype : Object.prototype);
}
// node_modules/.pnpm/es-toolkit@1.53.1/node_modules/es-toolkit/dist/compat/predicate/isEmpty.mjs
function ge(e) {
	if (e == null) return !0;
	if (le(e)) return typeof e.splice != "function" && typeof e != "string" && !ue(e) && !ne(e) && !de(e) ? !1 : e.length === 0;
	if (typeof e == "object" || typeof e == "function") {
		if (e instanceof Map || e instanceof Set) return e.size === 0;
		let t = Object.keys(e);
		return he(e) ? t.filter((e) => e !== "constructor").length === 0 : t.length === 0;
	}
	return !0;
}
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/chunk-EJLWFMJV.mjs
var _e = {
	common: C,
	getConfig: O,
	insertCluster: ee,
	insertEdge: I,
	insertEdgeLabel: P,
	insertMarkers: F,
	insertNode: R,
	interpolateToCurve: re,
	labelHelper: z,
	log: j,
	positionEdgeLabel: L
}, ve = [
	"elk.stress",
	"elk.force",
	"elk.mrtree",
	"elk.sporeOverlap",
	"elk.box",
	"elk.rectpacking"
], ye = /* @__PURE__ */ Object.create(null), be = /* @__PURE__ */ k((e) => {
	for (let t of e) ye[t.name] = t;
}, "registerLayoutLoaders"), xe = /* @__PURE__ */ k(() => {
	let e = /* @__PURE__ */ k(async () => await import("./elk-QONTBCTQ-DuBswTjE.js"), "loader");
	return [{
		name: "elk",
		loader: e,
		algorithm: "elk.layered"
	}, ...ve.map((t) => ({
		name: t,
		loader: e,
		algorithm: t
	}))];
}, "elkLayoutLoaders");
(/* @__PURE__ */ k(() => {
	be([
		{
			name: "dagre",
			loader: /* @__PURE__ */ k(async () => await import("./dagre-NK4GIZVX-Dr4xA66s.js"), "loader")
		},
		{
			name: "swimlane",
			loader: /* @__PURE__ */ k(async () => await import("./swimlanes-B3ENRKFQ-0pmLDBFQ.js"), "loader")
		},
		{
			name: "cose-bilkent",
			loader: /* @__PURE__ */ k(async () => await import("./cose-bilkent-JH36ORCC-JrUuWazz.js"), "loader")
		},
		...xe()
	]);
}, "registerDefaultLayoutLoaders"))();
var Se = /* @__PURE__ */ k(async (e, t) => {
	if (!Object.hasOwn(ye, e.layoutAlgorithm)) throw Error(`Unknown layout algorithm: ${e.layoutAlgorithm}`);
	if (e.diagramId) for (let t of e.nodes) {
		let n = t.domId || t.id;
		t.domId = `${e.diagramId}-${n}`;
	}
	let n = ye[e.layoutAlgorithm], r = await n.loader();
	return N(t, e.config), r.render(e, t, _e, { algorithm: n.algorithm });
}, "render"), Ce = "dagre", we = /* @__PURE__ */ k((e = "", { fallback: t = Ce } = {}) => {
	if (Object.hasOwn(ye, e)) return e;
	for (let n of [t, Ce]) if (Object.hasOwn(ye, n)) return j.warn(`Layout algorithm ${e} is not registered. Using ${n} as fallback.`), n;
	throw Error(`Neither layout algorithm ${e}, ${t}, nor ${Ce} is registered.`);
}, "getRegisteredLayoutAlgorithm");
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/chunk-LNGE3PJU.mjs
function Te(e) {
	return e && e.__esModule && Object.prototype.hasOwnProperty.call(e, "default") ? e.default : e;
}
k(Te, "getDefaultExportFromCjs");
var H = {}, Ee = {}, U = {}, De;
function W() {
	if (De) return U;
	De = 1;
	function e(e) {
		return e == null;
	}
	k(e, "isNothing");
	function t(e) {
		return typeof e == "object" && !!e;
	}
	k(t, "isObject");
	function n(t) {
		return Array.isArray(t) ? t : e(t) ? [] : [t];
	}
	k(n, "toArray");
	function r(e, t) {
		if (t) {
			let n = Object.keys(t);
			for (let r = 0, i = n.length; r < i; r += 1) {
				let i = n[r];
				e[i] = t[i];
			}
		}
		return e;
	}
	k(r, "extend");
	function i(e, t) {
		let n = "";
		for (let r = 0; r < t; r += 1) n += e;
		return n;
	}
	k(i, "repeat");
	function a(e) {
		return e === 0 && 1 / e == -Infinity;
	}
	return k(a, "isNegativeZero"), U.isNothing = e, U.isObject = t, U.toArray = n, U.repeat = i, U.isNegativeZero = a, U.extend = r, U;
}
k(W, "requireCommon");
var Oe, ke;
function G() {
	if (ke) return Oe;
	ke = 1;
	function e(e, t) {
		let n = "", r = e.reason || "(unknown reason)";
		return e.mark ? (e.mark.name && (n += "in \"" + e.mark.name + "\" "), n += "(" + (e.mark.line + 1) + ":" + (e.mark.column + 1) + ")", !t && e.mark.snippet && (n += "\n\n" + e.mark.snippet), r + " " + n) : r;
	}
	k(e, "formatError");
	function t(t, n) {
		Error.call(this), this.name = "YAMLException", this.reason = t, this.mark = n, this.message = e(this, !1), Error.captureStackTrace ? Error.captureStackTrace(this, this.constructor) : this.stack = (/* @__PURE__ */ Error()).stack || "";
	}
	return k(t, "YAMLException2"), t.prototype = Object.create(Error.prototype), t.prototype.constructor = t, t.prototype.toString = /* @__PURE__ */ k(function(t) {
		return this.name + ": " + e(this, t);
	}, "toString"), Oe = t, Oe;
}
k(G, "requireException");
var Ae, je;
function Me() {
	if (je) return Ae;
	je = 1;
	let e = W();
	function t(e, t, n, r, i) {
		let a = "", o = "", s = Math.floor(i / 2) - 1;
		return r - t > s && (a = " ... ", t = r - s + a.length), n - r > s && (o = " ...", n = r + s - o.length), {
			str: a + e.slice(t, n).replace(/\t/g, "→") + o,
			pos: r - t + a.length
		};
	}
	k(t, "getLine");
	function n(t, n) {
		return e.repeat(" ", n - t.length) + t;
	}
	k(n, "padStart");
	function r(r, i) {
		if (i = Object.create(i || null), !r.buffer) return null;
		i.maxLength || (i.maxLength = 79), typeof i.indent != "number" && (i.indent = 1), typeof i.linesBefore != "number" && (i.linesBefore = 3), typeof i.linesAfter != "number" && (i.linesAfter = 2);
		let a = /\r?\n|\r|\0/g, o = [0], s = [], c, l = -1;
		for (; c = a.exec(r.buffer);) s.push(c.index), o.push(c.index + c[0].length), r.position <= c.index && l < 0 && (l = o.length - 2);
		l < 0 && (l = o.length - 1);
		let u = "", d = Math.min(r.line + i.linesAfter, s.length).toString().length, f = i.maxLength - (i.indent + d + 3);
		for (let a = 1; a <= i.linesBefore && !(l - a < 0); a++) {
			let c = t(r.buffer, o[l - a], s[l - a], r.position - (o[l] - o[l - a]), f);
			u = e.repeat(" ", i.indent) + n((r.line - a + 1).toString(), d) + " | " + c.str + "\n" + u;
		}
		let p = t(r.buffer, o[l], s[l], r.position, f);
		u += e.repeat(" ", i.indent) + n((r.line + 1).toString(), d) + " | " + p.str + "\n", u += e.repeat("-", i.indent + d + 3 + p.pos) + "^\n";
		for (let a = 1; a <= i.linesAfter && !(l + a >= s.length); a++) {
			let c = t(r.buffer, o[l + a], s[l + a], r.position - (o[l] - o[l + a]), f);
			u += e.repeat(" ", i.indent) + n((r.line + a + 1).toString(), d) + " | " + c.str + "\n";
		}
		return u.replace(/\n$/, "");
	}
	return k(r, "makeSnippet"), Ae = r, Ae;
}
k(Me, "requireSnippet");
var Ne, Pe;
function K() {
	if (Pe) return Ne;
	Pe = 1;
	let e = G(), t = [
		"kind",
		"multi",
		"resolve",
		"construct",
		"instanceOf",
		"predicate",
		"represent",
		"representName",
		"defaultStyle",
		"styleAliases"
	], n = [
		"scalar",
		"sequence",
		"mapping"
	];
	function r(e) {
		let t = {};
		return e !== null && Object.keys(e).forEach(function(n) {
			e[n].forEach(function(e) {
				t[String(e)] = n;
			});
		}), t;
	}
	k(r, "compileStyleAliases");
	function i(i, a) {
		if (a ||= {}, Object.keys(a).forEach(function(n) {
			if (t.indexOf(n) === -1) throw new e("Unknown option \"" + n + "\" is met in definition of \"" + i + "\" YAML type.");
		}), this.options = a, this.tag = i, this.kind = a.kind || null, this.resolve = a.resolve || function() {
			return !0;
		}, this.construct = a.construct || function(e) {
			return e;
		}, this.instanceOf = a.instanceOf || null, this.predicate = a.predicate || null, this.represent = a.represent || null, this.representName = a.representName || null, this.defaultStyle = a.defaultStyle || null, this.multi = a.multi || !1, this.styleAliases = r(a.styleAliases || null), n.indexOf(this.kind) === -1) throw new e("Unknown kind \"" + this.kind + "\" is specified for \"" + i + "\" YAML type.");
	}
	return k(i, "Type2"), Ne = i, Ne;
}
k(K, "requireType");
var Fe, Ie;
function Le() {
	if (Ie) return Fe;
	Ie = 1;
	let e = G(), t = K();
	function n(e, t) {
		let n = [];
		return e[t].forEach(function(e) {
			let t = n.length;
			n.forEach(function(n, r) {
				n.tag === e.tag && n.kind === e.kind && n.multi === e.multi && (t = r);
			}), n[t] = e;
		}), n;
	}
	k(n, "compileList");
	function r() {
		let e = {
			scalar: {},
			sequence: {},
			mapping: {},
			fallback: {},
			multi: {
				scalar: [],
				sequence: [],
				mapping: [],
				fallback: []
			}
		};
		function t(t) {
			t.multi ? (e.multi[t.kind].push(t), e.multi.fallback.push(t)) : e[t.kind][t.tag] = e.fallback[t.tag] = t;
		}
		k(t, "collectType");
		for (let e = 0, n = arguments.length; e < n; e += 1) arguments[e].forEach(t);
		return e;
	}
	k(r, "compileMap");
	function i(e) {
		return this.extend(e);
	}
	return k(i, "Schema2"), i.prototype.extend = /* @__PURE__ */ k(function(a) {
		let o = [], s = [];
		if (a instanceof t) s.push(a);
		else if (Array.isArray(a)) s = s.concat(a);
		else if (a && (Array.isArray(a.implicit) || Array.isArray(a.explicit))) a.implicit && (o = o.concat(a.implicit)), a.explicit && (s = s.concat(a.explicit));
		else throw new e("Schema.extend argument should be a Type, [ Type ], or a schema definition ({ implicit: [...], explicit: [...] })");
		o.forEach(function(n) {
			if (!(n instanceof t)) throw new e("Specified list of YAML types (or a single Type object) contains a non-Type object.");
			if (n.loadKind && n.loadKind !== "scalar") throw new e("There is a non-scalar type in the implicit list of a schema. Implicit resolving of such types is not supported.");
			if (n.multi) throw new e("There is a multi type in the implicit list of a schema. Multi tags can only be listed as explicit.");
		}), s.forEach(function(n) {
			if (!(n instanceof t)) throw new e("Specified list of YAML types (or a single Type object) contains a non-Type object.");
		});
		let c = Object.create(i.prototype);
		return c.implicit = (this.implicit || []).concat(o), c.explicit = (this.explicit || []).concat(s), c.compiledImplicit = n(c, "implicit"), c.compiledExplicit = n(c, "explicit"), c.compiledTypeMap = r(c.compiledImplicit, c.compiledExplicit), c;
	}, "extend"), Fe = i, Fe;
}
k(Le, "requireSchema");
var Re, ze;
function Be() {
	return ze || (ze = 1, Re = new (K())("tag:yaml.org,2002:str", {
		kind: "scalar",
		construct: /* @__PURE__ */ k(function(e) {
			return e === null ? "" : e;
		}, "construct")
	})), Re;
}
k(Be, "requireStr");
var Ve, He;
function Ue() {
	return He || (He = 1, Ve = new (K())("tag:yaml.org,2002:seq", {
		kind: "sequence",
		construct: /* @__PURE__ */ k(function(e) {
			return e === null ? [] : e;
		}, "construct")
	})), Ve;
}
k(Ue, "requireSeq");
var We, Ge;
function Ke() {
	return Ge || (Ge = 1, We = new (K())("tag:yaml.org,2002:map", {
		kind: "mapping",
		construct: /* @__PURE__ */ k(function(e) {
			return e === null ? {} : e;
		}, "construct")
	})), We;
}
k(Ke, "requireMap");
var qe, Je;
function Ye() {
	return Je || (Je = 1, qe = new (Le())({ explicit: [
		Be(),
		Ue(),
		Ke()
	] })), qe;
}
k(Ye, "requireFailsafe");
var Xe, Ze;
function Qe() {
	if (Ze) return Xe;
	Ze = 1;
	let e = K();
	function t(e) {
		if (e === null) return !0;
		let t = e.length;
		return t === 1 && e === "~" || t === 4 && (e === "null" || e === "Null" || e === "NULL");
	}
	k(t, "resolveYamlNull");
	function n() {
		return null;
	}
	k(n, "constructYamlNull");
	function r(e) {
		return e === null;
	}
	return k(r, "isNull"), Xe = new e("tag:yaml.org,2002:null", {
		kind: "scalar",
		resolve: t,
		construct: n,
		predicate: r,
		represent: {
			canonical: /* @__PURE__ */ k(function() {
				return "~";
			}, "canonical"),
			lowercase: /* @__PURE__ */ k(function() {
				return "null";
			}, "lowercase"),
			uppercase: /* @__PURE__ */ k(function() {
				return "NULL";
			}, "uppercase"),
			camelcase: /* @__PURE__ */ k(function() {
				return "Null";
			}, "camelcase"),
			empty: /* @__PURE__ */ k(function() {
				return "";
			}, "empty")
		},
		defaultStyle: "lowercase"
	}), Xe;
}
k(Qe, "require_null");
var $e, et;
function tt() {
	if (et) return $e;
	et = 1;
	let e = K();
	function t(e) {
		if (e === null) return !1;
		let t = e.length;
		return t === 4 && (e === "true" || e === "True" || e === "TRUE") || t === 5 && (e === "false" || e === "False" || e === "FALSE");
	}
	k(t, "resolveYamlBoolean");
	function n(e) {
		return e === "true" || e === "True" || e === "TRUE";
	}
	k(n, "constructYamlBoolean");
	function r(e) {
		return Object.prototype.toString.call(e) === "[object Boolean]";
	}
	return k(r, "isBoolean"), $e = new e("tag:yaml.org,2002:bool", {
		kind: "scalar",
		resolve: t,
		construct: n,
		predicate: r,
		represent: {
			lowercase: /* @__PURE__ */ k(function(e) {
				return e ? "true" : "false";
			}, "lowercase"),
			uppercase: /* @__PURE__ */ k(function(e) {
				return e ? "TRUE" : "FALSE";
			}, "uppercase"),
			camelcase: /* @__PURE__ */ k(function(e) {
				return e ? "True" : "False";
			}, "camelcase")
		},
		defaultStyle: "lowercase"
	}), $e;
}
k(tt, "requireBool");
var nt, rt;
function it() {
	if (rt) return nt;
	rt = 1;
	let e = W(), t = K();
	function n(e) {
		return e >= 48 && e <= 57 || e >= 65 && e <= 70 || e >= 97 && e <= 102;
	}
	k(n, "isHexCode");
	function r(e) {
		return e >= 48 && e <= 55;
	}
	k(r, "isOctCode");
	function i(e) {
		return e >= 48 && e <= 57;
	}
	k(i, "isDecCode");
	function a(e) {
		if (e === null) return !1;
		let t = e.length, a = 0, s = !1;
		if (!t) return !1;
		let c = e[a];
		if ((c === "-" || c === "+") && (c = e[++a]), c === "0") {
			if (a + 1 === t) return !0;
			if (c = e[++a], c === "b") {
				for (a++; a < t; a++) {
					if (c = e[a], c !== "0" && c !== "1") return !1;
					s = !0;
				}
				return s && isFinite(o(e));
			}
			if (c === "x") {
				for (a++; a < t; a++) {
					if (!n(e.charCodeAt(a))) return !1;
					s = !0;
				}
				return s && isFinite(o(e));
			}
			if (c === "o") {
				for (a++; a < t; a++) {
					if (!r(e.charCodeAt(a))) return !1;
					s = !0;
				}
				return s && isFinite(o(e));
			}
		}
		for (; a < t; a++) {
			if (!i(e.charCodeAt(a))) return !1;
			s = !0;
		}
		return s ? isFinite(o(e)) : !1;
	}
	k(a, "resolveYamlInteger");
	function o(e) {
		let t = e, n = 1, r = t[0];
		if ((r === "-" || r === "+") && (r === "-" && (n = -1), t = t.slice(1), r = t[0]), t === "0") return 0;
		if (r === "0") {
			if (t[1] === "b") return n * parseInt(t.slice(2), 2);
			if (t[1] === "x") return n * parseInt(t.slice(2), 16);
			if (t[1] === "o") return n * parseInt(t.slice(2), 8);
		}
		return n * parseInt(t, 10);
	}
	k(o, "parseYamlInteger");
	function s(e) {
		return o(e);
	}
	k(s, "constructYamlInteger");
	function c(t) {
		return Object.prototype.toString.call(t) === "[object Number]" && t % 1 == 0 && !e.isNegativeZero(t);
	}
	return k(c, "isInteger"), nt = new t("tag:yaml.org,2002:int", {
		kind: "scalar",
		resolve: a,
		construct: s,
		predicate: c,
		represent: {
			binary: /* @__PURE__ */ k(function(e) {
				return e >= 0 ? "0b" + e.toString(2) : "-0b" + e.toString(2).slice(1);
			}, "binary"),
			octal: /* @__PURE__ */ k(function(e) {
				return e >= 0 ? "0o" + e.toString(8) : "-0o" + e.toString(8).slice(1);
			}, "octal"),
			decimal: /* @__PURE__ */ k(function(e) {
				return e.toString(10);
			}, "decimal"),
			hexadecimal: /* @__PURE__ */ k(function(e) {
				return e >= 0 ? "0x" + e.toString(16).toUpperCase() : "-0x" + e.toString(16).toUpperCase().slice(1);
			}, "hexadecimal")
		},
		defaultStyle: "decimal",
		styleAliases: {
			binary: [2, "bin"],
			octal: [8, "oct"],
			decimal: [10, "dec"],
			hexadecimal: [16, "hex"]
		}
	}), nt;
}
k(it, "requireInt");
var at, ot;
function st() {
	if (ot) return at;
	ot = 1;
	let e = W(), t = K(), n = /* @__PURE__ */ RegExp("^(?:[-+]?(?:[0-9]+)(?:\\.[0-9]*)?(?:[eE][-+]?[0-9]+)?|\\.[0-9]+(?:[eE][-+]?[0-9]+)?|[-+]?\\.(?:inf|Inf|INF)|\\.(?:nan|NaN|NAN))$"), r = /* @__PURE__ */ RegExp("^(?:[-+]?\\.(?:inf|Inf|INF)|\\.(?:nan|NaN|NAN))$");
	function i(e) {
		return e === null || !n.test(e) ? !1 : isFinite(parseFloat(e, 10)) ? !0 : r.test(e);
	}
	k(i, "resolveYamlFloat");
	function a(e) {
		let t = e.toLowerCase(), n = t[0] === "-" ? -1 : 1;
		return "+-".indexOf(t[0]) >= 0 && (t = t.slice(1)), t === ".inf" ? n === 1 ? Infinity : -Infinity : t === ".nan" ? NaN : n * parseFloat(t, 10);
	}
	k(a, "constructYamlFloat");
	let o = /^[-+]?[0-9]+e/;
	function s(t, n) {
		if (isNaN(t)) switch (n) {
			case "lowercase": return ".nan";
			case "uppercase": return ".NAN";
			case "camelcase": return ".NaN";
		}
		else if (t === Infinity) switch (n) {
			case "lowercase": return ".inf";
			case "uppercase": return ".INF";
			case "camelcase": return ".Inf";
		}
		else if (t === -Infinity) switch (n) {
			case "lowercase": return "-.inf";
			case "uppercase": return "-.INF";
			case "camelcase": return "-.Inf";
		}
		else if (e.isNegativeZero(t)) return "-0.0";
		let r = t.toString(10);
		return o.test(r) ? r.replace("e", ".e") : r;
	}
	k(s, "representYamlFloat");
	function c(t) {
		return Object.prototype.toString.call(t) === "[object Number]" && (t % 1 != 0 || e.isNegativeZero(t));
	}
	return k(c, "isFloat"), at = new t("tag:yaml.org,2002:float", {
		kind: "scalar",
		resolve: i,
		construct: a,
		predicate: c,
		represent: s,
		defaultStyle: "lowercase"
	}), at;
}
k(st, "requireFloat");
var ct, lt;
function ut() {
	return lt || (lt = 1, ct = Ye().extend({ implicit: [
		Qe(),
		tt(),
		it(),
		st()
	] })), ct;
}
k(ut, "requireJson");
var dt, ft;
function pt() {
	return ft || (ft = 1, dt = ut()), dt;
}
k(pt, "requireCore");
var mt, ht;
function gt() {
	if (ht) return mt;
	ht = 1;
	let e = K(), t = /* @__PURE__ */ RegExp("^([0-9][0-9][0-9][0-9])-([0-9][0-9])-([0-9][0-9])$"), n = /* @__PURE__ */ RegExp("^([0-9][0-9][0-9][0-9])-([0-9][0-9]?)-([0-9][0-9]?)(?:[Tt]|[ \\t]+)([0-9][0-9]?):([0-9][0-9]):([0-9][0-9])(?:\\.([0-9]*))?(?:[ \\t]*(Z|([-+])([0-9][0-9]?)(?::([0-9][0-9]))?))?$");
	function r(e) {
		return e === null ? !1 : t.exec(e) !== null || n.exec(e) !== null;
	}
	k(r, "resolveYamlTimestamp");
	function i(e) {
		let r = 0, i = null, a = t.exec(e);
		if (a === null && (a = n.exec(e)), a === null) throw Error("Date resolve error");
		let o = +a[1], s = a[2] - 1, c = +a[3];
		if (!a[4]) return new Date(Date.UTC(o, s, c));
		let l = +a[4], u = +a[5], d = +a[6];
		if (a[7]) {
			for (r = a[7].slice(0, 3); r.length < 3;) r += "0";
			r = +r;
		}
		if (a[9]) {
			let e = +a[10], t = +(a[11] || 0);
			i = (e * 60 + t) * 6e4, a[9] === "-" && (i = -i);
		}
		let f = new Date(Date.UTC(o, s, c, l, u, d, r));
		return i && f.setTime(f.getTime() - i), f;
	}
	k(i, "constructYamlTimestamp");
	function a(e) {
		return e.toISOString();
	}
	return k(a, "representYamlTimestamp"), mt = new e("tag:yaml.org,2002:timestamp", {
		kind: "scalar",
		resolve: r,
		construct: i,
		instanceOf: Date,
		represent: a
	}), mt;
}
k(gt, "requireTimestamp");
var _t, vt;
function yt() {
	if (vt) return _t;
	vt = 1;
	let e = K();
	function t(e) {
		return e === "<<" || e === null;
	}
	return k(t, "resolveYamlMerge"), _t = new e("tag:yaml.org,2002:merge", {
		kind: "scalar",
		resolve: t
	}), _t;
}
k(yt, "requireMerge");
var bt, xt;
function St() {
	if (xt) return bt;
	xt = 1;
	let e = K();
	function t(e) {
		if (e === null) return !1;
		let t = 0, n = e.length;
		for (let r = 0; r < n; r++) {
			let n = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=\n\r".indexOf(e.charAt(r));
			if (!(n > 64)) {
				if (n < 0) return !1;
				t += 6;
			}
		}
		return t % 8 == 0;
	}
	k(t, "resolveYamlBinary");
	function n(e) {
		let t = e.replace(/[\r\n=]/g, ""), n = t.length, r = 0, i = [];
		for (let e = 0; e < n; e++) e % 4 == 0 && e && (i.push(r >> 16 & 255), i.push(r >> 8 & 255), i.push(r & 255)), r = r << 6 | "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=\n\r".indexOf(t.charAt(e));
		let a = n % 4 * 6;
		return a === 0 ? (i.push(r >> 16 & 255), i.push(r >> 8 & 255), i.push(r & 255)) : a === 18 ? (i.push(r >> 10 & 255), i.push(r >> 2 & 255)) : a === 12 && i.push(r >> 4 & 255), new Uint8Array(i);
	}
	k(n, "constructYamlBinary");
	function r(e) {
		let t = "", n = 0, r = e.length, i = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=\n\r";
		for (let a = 0; a < r; a++) a % 3 == 0 && a && (t += i[n >> 18 & 63], t += i[n >> 12 & 63], t += i[n >> 6 & 63], t += i[n & 63]), n = (n << 8) + e[a];
		let a = r % 3;
		return a === 0 ? (t += i[n >> 18 & 63], t += i[n >> 12 & 63], t += i[n >> 6 & 63], t += i[n & 63]) : a === 2 ? (t += i[n >> 10 & 63], t += i[n >> 4 & 63], t += i[n << 2 & 63], t += i[64]) : a === 1 && (t += i[n >> 2 & 63], t += i[n << 4 & 63], t += i[64], t += i[64]), t;
	}
	k(r, "representYamlBinary");
	function i(e) {
		return Object.prototype.toString.call(e) === "[object Uint8Array]";
	}
	return k(i, "isBinary"), bt = new e("tag:yaml.org,2002:binary", {
		kind: "scalar",
		resolve: t,
		construct: n,
		predicate: i,
		represent: r
	}), bt;
}
k(St, "requireBinary");
var Ct, wt;
function Tt() {
	if (wt) return Ct;
	wt = 1;
	let e = K(), t = Object.prototype.hasOwnProperty, n = Object.prototype.toString;
	function r(e) {
		if (e === null) return !0;
		let r = [], i = e;
		for (let e = 0, a = i.length; e < a; e += 1) {
			let a = i[e], o = !1;
			if (n.call(a) !== "[object Object]") return !1;
			let s;
			for (s in a) if (t.call(a, s)) {
				if (!o) o = !0;
				else return !1;
			}
			if (!o) return !1;
			if (r.indexOf(s) === -1) r.push(s);
			else return !1;
		}
		return !0;
	}
	k(r, "resolveYamlOmap");
	function i(e) {
		return e === null ? [] : e;
	}
	return k(i, "constructYamlOmap"), Ct = new e("tag:yaml.org,2002:omap", {
		kind: "sequence",
		resolve: r,
		construct: i
	}), Ct;
}
k(Tt, "requireOmap");
var Et, Dt;
function Ot() {
	if (Dt) return Et;
	Dt = 1;
	let e = K(), t = Object.prototype.toString;
	function n(e) {
		if (e === null) return !0;
		let n = e, r = Array(n.length);
		for (let e = 0, i = n.length; e < i; e += 1) {
			let i = n[e];
			if (t.call(i) !== "[object Object]") return !1;
			let a = Object.keys(i);
			if (a.length !== 1) return !1;
			r[e] = [a[0], i[a[0]]];
		}
		return !0;
	}
	k(n, "resolveYamlPairs");
	function r(e) {
		if (e === null) return [];
		let t = e, n = Array(t.length);
		for (let e = 0, r = t.length; e < r; e += 1) {
			let r = t[e], i = Object.keys(r);
			n[e] = [i[0], r[i[0]]];
		}
		return n;
	}
	return k(r, "constructYamlPairs"), Et = new e("tag:yaml.org,2002:pairs", {
		kind: "sequence",
		resolve: n,
		construct: r
	}), Et;
}
k(Ot, "requirePairs");
var kt, At;
function jt() {
	if (At) return kt;
	At = 1;
	let e = K(), t = Object.prototype.hasOwnProperty;
	function n(e) {
		if (e === null) return !0;
		let n = e;
		for (let e in n) if (t.call(n, e) && n[e] !== null) return !1;
		return !0;
	}
	k(n, "resolveYamlSet");
	function r(e) {
		return e === null ? {} : e;
	}
	return k(r, "constructYamlSet"), kt = new e("tag:yaml.org,2002:set", {
		kind: "mapping",
		resolve: n,
		construct: r
	}), kt;
}
k(jt, "requireSet");
var Mt, Nt;
function Pt() {
	return Nt || (Nt = 1, Mt = pt().extend({
		implicit: [gt(), yt()],
		explicit: [
			St(),
			Tt(),
			Ot(),
			jt()
		]
	})), Mt;
}
k(Pt, "require_default");
var Ft;
function It() {
	if (Ft) return Ee;
	Ft = 1;
	let e = W(), t = G(), n = Me(), r = Pt(), i = Object.prototype.hasOwnProperty, a = /[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x84\x86-\x9F\uFFFE\uFFFF]|[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?:[^\uD800-\uDBFF]|^)[\uDC00-\uDFFF]/, o = /[\x85\u2028\u2029]/, s = /[,\[\]{}]/, c = /^(?:!|!!|![0-9A-Za-z-]+!)$/, l = /^(?:!|[^,\[\]{}])(?:%[0-9a-f]{2}|[0-9a-z\-#;/?:@&=+$,_.!~*'()\[\]])*$/i;
	function u(e) {
		return Object.prototype.toString.call(e);
	}
	k(u, "_class");
	function d(e) {
		return e === 10 || e === 13;
	}
	k(d, "isEol");
	function f(e) {
		return e === 9 || e === 32;
	}
	k(f, "isWhiteSpace");
	function p(e) {
		return e === 9 || e === 32 || e === 10 || e === 13;
	}
	k(p, "isWsOrEol");
	function m(e) {
		return e === 44 || e === 91 || e === 93 || e === 123 || e === 125;
	}
	k(m, "isFlowIndicator");
	function h(e) {
		if (e >= 48 && e <= 57) return e - 48;
		let t = e | 32;
		return t >= 97 && t <= 102 ? t - 97 + 10 : -1;
	}
	k(h, "fromHexCode");
	function g(e) {
		return e === 120 ? 2 : e === 117 ? 4 : e === 85 ? 8 : 0;
	}
	k(g, "escapedHexLen");
	function _(e) {
		return e >= 48 && e <= 57 ? e - 48 : -1;
	}
	k(_, "fromDecimalCode");
	function v(e) {
		switch (e) {
			case 48: return "\0";
			case 97: return "\x07";
			case 98: return "\b";
			case 116: return "	";
			case 9: return "	";
			case 110: return "\n";
			case 118: return "\v";
			case 102: return "\f";
			case 114: return "\r";
			case 101: return "\x1B";
			case 32: return " ";
			case 34: return "\"";
			case 47: return "/";
			case 92: return "\\";
			case 78: return "";
			case 95: return "\xA0";
			case 76: return "\u2028";
			case 80: return "\u2029";
			default: return "";
		}
	}
	k(v, "simpleEscapeSequence");
	function y(e) {
		return e <= 65535 ? String.fromCharCode(e) : String.fromCharCode((e - 65536 >> 10) + 55296, (e - 65536 & 1023) + 56320);
	}
	k(y, "charFromCodepoint");
	function b(e, t, n) {
		t === "__proto__" ? Object.defineProperty(e, t, {
			configurable: !0,
			enumerable: !0,
			writable: !0,
			value: n
		}) : e[t] = n;
	}
	k(b, "setProperty");
	let x = Array(256), S = Array(256);
	for (let e = 0; e < 256; e++) x[e] = +!!v(e), S[e] = v(e);
	function C(e, t) {
		this.input = e, this.filename = t.filename || null, this.schema = t.schema || r, this.onWarning = t.onWarning || null, this.legacy = t.legacy || !1, this.json = t.json || !1, this.listener = t.listener || null, this.maxDepth = typeof t.maxDepth == "number" ? t.maxDepth : 100, this.maxTotalMergeKeys = typeof t.maxTotalMergeKeys == "number" ? t.maxTotalMergeKeys : 1e4, this.implicitTypes = this.schema.compiledImplicit, this.typeMap = this.schema.compiledTypeMap, this.length = e.length, this.position = 0, this.line = 0, this.lineStart = 0, this.lineIndent = 0, this.depth = 0, this.totalMergeKeys = 0, this.firstTabInLine = -1, this.documents = [], this.anchorMapTransactions = [];
	}
	k(C, "State");
	function w(e, r) {
		let i = {
			name: e.filename,
			buffer: e.input.slice(0, -1),
			position: e.position,
			line: e.line,
			column: e.position - e.lineStart
		};
		return i.snippet = n(i), new t(r, i);
	}
	k(w, "generateError");
	function T(e, t) {
		throw w(e, t);
	}
	k(T, "throwError");
	function E(e, t) {
		e.onWarning && e.onWarning.call(null, w(e, t));
	}
	k(E, "throwWarning");
	function D(e, t, n) {
		let r = e.anchorMapTransactions;
		if (r.length !== 0) {
			let n = r[r.length - 1];
			i.call(n, t) || (n[t] = {
				existed: i.call(e.anchorMap, t),
				value: e.anchorMap[t]
			});
		}
		e.anchorMap[t] = n;
	}
	k(D, "storeAnchor");
	function O(e) {
		e.anchorMapTransactions.push(/* @__PURE__ */ Object.create(null));
	}
	k(O, "beginAnchorTransaction");
	function A(e) {
		let t = e.anchorMapTransactions.pop(), n = e.anchorMapTransactions;
		if (n.length === 0) return;
		let r = n[n.length - 1], a = Object.keys(t);
		for (let e = 0, n = a.length; e < n; e += 1) {
			let n = a[e];
			i.call(r, n) || (r[n] = t[n]);
		}
	}
	k(A, "commitAnchorTransaction");
	function j(e) {
		let t = e.anchorMapTransactions.pop(), n = Object.keys(t);
		for (let r = n.length - 1; r >= 0; --r) {
			let i = t[n[r]];
			i.existed ? e.anchorMap[n[r]] = i.value : delete e.anchorMap[n[r]];
		}
	}
	k(j, "rollbackAnchorTransaction");
	function M(e) {
		return {
			position: e.position,
			line: e.line,
			lineStart: e.lineStart,
			lineIndent: e.lineIndent,
			firstTabInLine: e.firstTabInLine,
			tag: e.tag,
			anchor: e.anchor,
			kind: e.kind,
			result: e.result
		};
	}
	k(M, "snapshotState");
	function N(e, t) {
		e.position = t.position, e.line = t.line, e.lineStart = t.lineStart, e.lineIndent = t.lineIndent, e.firstTabInLine = t.firstTabInLine, e.tag = t.tag, e.anchor = t.anchor, e.kind = t.kind, e.result = t.result;
	}
	k(N, "restoreState");
	let ee = {
		YAML: /* @__PURE__ */ k(function(e, t, n) {
			e.version !== null && T(e, "duplication of %YAML directive"), n.length !== 1 && T(e, "YAML directive accepts exactly one argument");
			let r = /^([0-9]+)\.([0-9]+)$/.exec(n[0]);
			r === null && T(e, "ill-formed argument of the YAML directive");
			let i = parseInt(r[1], 10), a = parseInt(r[2], 10);
			i !== 1 && T(e, "unacceptable YAML version of the document"), e.version = n[0], e.checkLineBreaks = a < 2, a !== 1 && a !== 2 && E(e, "unsupported YAML version of the document");
		}, "handleYamlDirective"),
		TAG: /* @__PURE__ */ k(function(e, t, n) {
			let r;
			n.length !== 2 && T(e, "TAG directive accepts exactly two arguments");
			let a = n[0];
			r = n[1], c.test(a) || T(e, "ill-formed tag handle (first argument) of the TAG directive"), i.call(e.tagMap, a) && T(e, "there is a previously declared suffix for \"" + a + "\" tag handle"), l.test(r) || T(e, "ill-formed tag prefix (second argument) of the TAG directive");
			try {
				r = decodeURIComponent(r);
			} catch {
				T(e, "tag prefix is malformed: " + r);
			}
			e.tagMap[a] = r;
		}, "handleTagDirective")
	};
	function P(e, t, n, r) {
		if (t < n) {
			let i = e.input.slice(t, n);
			if (r) for (let t = 0, n = i.length; t < n; t += 1) {
				let n = i.charCodeAt(t);
				n === 9 || n >= 32 && n <= 1114111 || T(e, "expected valid JSON character");
			}
			else a.test(i) && T(e, "the stream contains non-printable characters");
			e.result += i;
		}
	}
	k(P, "captureSegment");
	function F(t, n, r, a) {
		e.isObject(r) || T(t, "cannot merge mappings; the provided source object is unacceptable");
		let o = Object.keys(r);
		for (let e = 0, s = o.length; e < s; e += 1) {
			let s = o[e];
			t.maxTotalMergeKeys !== -1 && ++t.totalMergeKeys > t.maxTotalMergeKeys && T(t, "merge keys exceeded maxTotalMergeKeys (" + t.maxTotalMergeKeys + ")"), i.call(n, s) || (b(n, s, r[s]), a[s] = !0);
		}
	}
	k(F, "mergeMappings");
	function I(e, t, n, r, a, o, s, c, l) {
		if (Array.isArray(a)) {
			a = Array.prototype.slice.call(a);
			for (let t = 0, n = a.length; t < n; t += 1) Array.isArray(a[t]) && T(e, "nested arrays are not supported inside keys"), typeof a == "object" && u(a[t]) === "[object Object]" && (a[t] = "[object Object]");
		}
		if (typeof a == "object" && u(a) === "[object Object]" && (a = "[object Object]"), a = String(a), t === null && (t = {}), r === "tag:yaml.org,2002:merge") {
			if (Array.isArray(o)) for (let r = 0, i = o.length; r < i; r += 1) F(e, t, o[r], n);
			else F(e, t, o, n);
		} else !e.json && !i.call(n, a) && i.call(t, a) && (e.line = s || e.line, e.lineStart = c || e.lineStart, e.position = l || e.position, T(e, "duplicated mapping key")), b(t, a, o), delete n[a];
		return t;
	}
	k(I, "storeMappingPair");
	function L(e) {
		let t = e.input.charCodeAt(e.position);
		t === 10 ? e.position++ : t === 13 ? (e.position++, e.input.charCodeAt(e.position) === 10 && e.position++) : T(e, "a line break is expected"), e.line += 1, e.lineStart = e.position, e.firstTabInLine = -1;
	}
	k(L, "readLineBreak");
	function R(e, t, n) {
		let r = 0, i = e.input.charCodeAt(e.position);
		for (; i !== 0;) {
			for (; f(i);) i === 9 && e.firstTabInLine === -1 && (e.firstTabInLine = e.position), i = e.input.charCodeAt(++e.position);
			if (t && i === 35) do
				i = e.input.charCodeAt(++e.position);
			while (i !== 10 && i !== 13 && i !== 0);
			if (d(i)) for (L(e), i = e.input.charCodeAt(e.position), r++, e.lineIndent = 0; i === 32;) e.lineIndent++, i = e.input.charCodeAt(++e.position);
			else break;
		}
		return n !== -1 && r !== 0 && e.lineIndent < n && E(e, "deficient indentation"), r;
	}
	k(R, "skipSeparationSpace");
	function z(e) {
		let t = e.position, n = e.input.charCodeAt(t);
		return !!((n === 45 || n === 46) && n === e.input.charCodeAt(t + 1) && n === e.input.charCodeAt(t + 2) && (t += 3, n = e.input.charCodeAt(t), n === 0 || p(n)));
	}
	k(z, "testDocumentSeparator");
	function B(t, n) {
		n === 1 ? t.result += " " : n > 1 && (t.result += e.repeat("\n", n - 1));
	}
	k(B, "writeFoldedLines");
	function te(e, t, n) {
		let r, i, a, o, s, c, l = e.kind, u = e.result, h = e.input.charCodeAt(e.position);
		if (p(h) || m(h) || h === 35 || h === 38 || h === 42 || h === 33 || h === 124 || h === 62 || h === 39 || h === 34 || h === 37 || h === 64 || h === 96) return !1;
		if (h === 63 || h === 45) {
			let t = e.input.charCodeAt(e.position + 1);
			if (p(t) || n && m(t)) return !1;
		}
		for (e.kind = "scalar", e.result = "", r = i = e.position, a = !1; h !== 0;) {
			if (h === 58) {
				let t = e.input.charCodeAt(e.position + 1);
				if (p(t) || n && m(t)) break;
			} else if (h === 35) {
				if (p(e.input.charCodeAt(e.position - 1))) break;
			} else if (e.position === e.lineStart && z(e) || n && m(h)) break;
			else if (d(h)) {
				if (o = e.line, s = e.lineStart, c = e.lineIndent, R(e, !1, -1), e.lineIndent >= t) {
					a = !0, h = e.input.charCodeAt(e.position);
					continue;
				}
				e.position = i, e.line = o, e.lineStart = s, e.lineIndent = c;
				break;
			}
			a &&= (P(e, r, i, !1), B(e, e.line - o), r = i = e.position, !1), f(h) || (i = e.position + 1), h = e.input.charCodeAt(++e.position);
		}
		return P(e, r, i, !1), e.result ? !0 : (e.kind = l, e.result = u, !1);
	}
	k(te, "readPlainScalar");
	function ne(e, t) {
		let n, r, i = e.input.charCodeAt(e.position);
		if (i !== 39) return !1;
		for (e.kind = "scalar", e.result = "", e.position++, n = r = e.position; (i = e.input.charCodeAt(e.position)) !== 0;) if (i === 39) {
			if (P(e, n, e.position, !0), i = e.input.charCodeAt(++e.position), i === 39) n = e.position, e.position++, r = e.position;
			else return !0;
		} else d(i) ? (P(e, n, r, !0), B(e, R(e, !1, t)), n = r = e.position) : e.position === e.lineStart && z(e) ? T(e, "unexpected end of the document within a single quoted scalar") : (e.position++, f(i) || (r = e.position));
		T(e, "unexpected end of the stream within a single quoted scalar");
	}
	k(ne, "readSingleQuotedScalar");
	function re(e, t) {
		let n, r, i, a = e.input.charCodeAt(e.position);
		if (a !== 34) return !1;
		for (e.kind = "scalar", e.result = "", e.position++, n = r = e.position; (a = e.input.charCodeAt(e.position)) !== 0;) if (a === 34) return P(e, n, e.position, !0), e.position++, !0;
		else if (a === 92) {
			if (P(e, n, e.position, !0), a = e.input.charCodeAt(++e.position), d(a)) R(e, !1, t);
			else if (a < 256 && x[a]) e.result += S[a], e.position++;
			else if ((i = g(a)) > 0) {
				let t = i, n = 0;
				for (; t > 0; t--) a = e.input.charCodeAt(++e.position), (i = h(a)) >= 0 ? n = (n << 4) + i : T(e, "expected hexadecimal character");
				e.result += y(n), e.position++;
			} else T(e, "unknown escape sequence");
			n = r = e.position;
		} else d(a) ? (P(e, n, r, !0), B(e, R(e, !1, t)), n = r = e.position) : e.position === e.lineStart && z(e) ? T(e, "unexpected end of the document within a double quoted scalar") : (e.position++, f(a) || (r = e.position));
		T(e, "unexpected end of the stream within a double quoted scalar");
	}
	k(re, "readDoubleQuotedScalar");
	function ie(e, t) {
		let n = !0, r, i, a, o = e.tag, s, c = e.anchor, l, u, d, f, m = /* @__PURE__ */ Object.create(null), h, g, _, v = e.input.charCodeAt(e.position);
		if (v === 91) l = 93, f = !1, s = [];
		else if (v === 123) l = 125, f = !0, s = {};
		else return !1;
		for (e.anchor !== null && D(e, e.anchor, s), v = e.input.charCodeAt(++e.position); v !== 0;) {
			if (R(e, !0, t), v = e.input.charCodeAt(e.position), v === l) return e.position++, e.tag = o, e.anchor = c, e.kind = f ? "mapping" : "sequence", e.result = s, !0;
			n ? v === 44 && T(e, "expected the node content, but found ','") : T(e, "missed comma between flow collection entries"), g = h = _ = null, u = d = !1, v === 63 && p(e.input.charCodeAt(e.position + 1)) && (u = d = !0, e.position++, R(e, !0, t)), r = e.line, i = e.lineStart, a = e.position, V(e, t, 1, !1, !0), g = e.tag, h = e.result, R(e, !0, t), v = e.input.charCodeAt(e.position), (d || e.line === r) && v === 58 && (u = !0, v = e.input.charCodeAt(++e.position), R(e, !0, t), V(e, t, 1, !1, !0), _ = e.result), f ? I(e, s, m, g, h, _, r, i, a) : u ? s.push(I(e, null, m, g, h, _, r, i, a)) : s.push(h), R(e, !0, t), v = e.input.charCodeAt(e.position), v === 44 ? (n = !0, v = e.input.charCodeAt(++e.position)) : n = !1;
		}
		T(e, "unexpected end of the stream within a flow collection");
	}
	k(ie, "readFlowCollection");
	function ae(t, n) {
		let r, i = 1, a = !1, o = !1, s = n, c = 0, l = !1, u, p = t.input.charCodeAt(t.position);
		if (p === 124) r = !1;
		else if (p === 62) r = !0;
		else return !1;
		for (t.kind = "scalar", t.result = ""; p !== 0;) if (p = t.input.charCodeAt(++t.position), p === 43 || p === 45) i === 1 ? i = p === 43 ? 3 : 2 : T(t, "repeat of a chomping mode identifier");
		else if ((u = _(p)) >= 0) u === 0 ? T(t, "bad explicit indentation width of a block scalar; it cannot be less than one") : o ? T(t, "repeat of an indentation width identifier") : (s = n + u - 1, o = !0);
		else break;
		if (f(p)) {
			do
				p = t.input.charCodeAt(++t.position);
			while (f(p));
			if (p === 35) do
				p = t.input.charCodeAt(++t.position);
			while (!d(p) && p !== 0);
		}
		for (; p !== 0;) {
			for (L(t), t.lineIndent = 0, p = t.input.charCodeAt(t.position); (!o || t.lineIndent < s) && p === 32;) t.lineIndent++, p = t.input.charCodeAt(++t.position);
			if (!o && t.lineIndent > s && (s = t.lineIndent), d(p)) {
				c++;
				continue;
			}
			if (!o && s === 0 && T(t, "missing indentation for block scalar"), t.lineIndent < s) {
				i === 3 ? t.result += e.repeat("\n", a ? 1 + c : c) : i === 1 && a && (t.result += "\n");
				break;
			}
			r ? f(p) ? (l = !0, t.result += e.repeat("\n", a ? 1 + c : c)) : l ? (l = !1, t.result += e.repeat("\n", c + 1)) : c === 0 ? a && (t.result += " ") : t.result += e.repeat("\n", c) : t.result += e.repeat("\n", a ? 1 + c : c), a = !0, o = !0, c = 0;
			let n = t.position;
			for (; !d(p) && p !== 0;) p = t.input.charCodeAt(++t.position);
			P(t, n, t.position, !1);
		}
		return !0;
	}
	k(ae, "readBlockScalar");
	function oe(e, t) {
		let n = e.tag, r = e.anchor, i = [], a = !1;
		if (e.firstTabInLine !== -1) return !1;
		e.anchor !== null && D(e, e.anchor, i);
		let o = e.input.charCodeAt(e.position);
		for (; o !== 0 && (e.firstTabInLine !== -1 && (e.position = e.firstTabInLine, T(e, "tab characters must not be used in indentation")), o === 45 && p(e.input.charCodeAt(e.position + 1)));) {
			if (a = !0, e.position++, R(e, !0, -1) && e.lineIndent <= t) {
				i.push(null), o = e.input.charCodeAt(e.position);
				continue;
			}
			let n = e.line;
			if (V(e, t, 3, !1, !0), i.push(e.result), R(e, !0, -1), o = e.input.charCodeAt(e.position), (e.line === n || e.lineIndent > t) && o !== 0) T(e, "bad indentation of a sequence entry");
			else if (e.lineIndent < t) break;
		}
		return a ? (e.tag = n, e.anchor = r, e.kind = "sequence", e.result = i, !0) : !1;
	}
	k(oe, "readBlockSequence");
	function se(e, t, n) {
		let r, i, a, o, s = e.tag, c = e.anchor, l = {}, u = /* @__PURE__ */ Object.create(null), d = null, m = null, h = null, g = !1, _ = !1;
		if (e.firstTabInLine !== -1) return !1;
		e.anchor !== null && D(e, e.anchor, l);
		let v = e.input.charCodeAt(e.position);
		for (; v !== 0;) {
			!g && e.firstTabInLine !== -1 && (e.position = e.firstTabInLine, T(e, "tab characters must not be used in indentation"));
			let y = e.input.charCodeAt(e.position + 1), b = e.line;
			if ((v === 63 || v === 58) && p(y)) v === 63 ? (g && (I(e, l, u, d, m, null, i, a, o), d = m = h = null), _ = !0, g = !0, r = !0) : g ? (g = !1, r = !0) : T(e, "incomplete explicit mapping pair; a key node is missed; or followed by a non-tabulated empty line"), e.position += 1, v = y;
			else {
				if (i = e.line, a = e.lineStart, o = e.position, !V(e, n, 2, !1, !0)) break;
				if (e.line === b) {
					for (v = e.input.charCodeAt(e.position); f(v);) v = e.input.charCodeAt(++e.position);
					if (v === 58) v = e.input.charCodeAt(++e.position), p(v) || T(e, "a whitespace character is expected after the key-value separator within a block mapping"), g && (I(e, l, u, d, m, null, i, a, o), d = m = h = null), _ = !0, g = !1, r = !1, d = e.tag, m = e.result;
					else if (_) T(e, "can not read an implicit mapping pair; a colon is missed");
					else return e.tag = s, e.anchor = c, !0;
				} else if (_) T(e, "can not read a block mapping entry; a multiline key may not be an implicit key");
				else return e.tag = s, e.anchor = c, !0;
			}
			if ((e.line === b || e.lineIndent > t) && (g && (i = e.line, a = e.lineStart, o = e.position), V(e, t, 4, !0, r) && (g ? m = e.result : h = e.result), g || (I(e, l, u, d, m, h, i, a, o), d = m = h = null), R(e, !0, -1), v = e.input.charCodeAt(e.position)), (e.line === b || e.lineIndent > t) && v !== 0) T(e, "bad indentation of a mapping entry");
			else if (e.lineIndent < t) break;
		}
		return g && I(e, l, u, d, m, null, i, a, o), _ && (e.tag = s, e.anchor = c, e.kind = "mapping", e.result = l), _;
	}
	k(se, "readBlockMapping");
	function ce(e) {
		let t = !1, n = !1, r, a, o = e.input.charCodeAt(e.position);
		if (o !== 33) return !1;
		e.tag !== null && T(e, "duplication of a tag property"), o = e.input.charCodeAt(++e.position), o === 60 ? (t = !0, o = e.input.charCodeAt(++e.position)) : o === 33 ? (n = !0, r = "!!", o = e.input.charCodeAt(++e.position)) : r = "!";
		let u = e.position;
		if (t) {
			do
				o = e.input.charCodeAt(++e.position);
			while (o !== 0 && o !== 62);
			e.position < e.length ? (a = e.input.slice(u, e.position), o = e.input.charCodeAt(++e.position)) : T(e, "unexpected end of the stream within a verbatim tag");
		} else {
			for (; o !== 0 && !p(o);) o === 33 && (n ? T(e, "tag suffix cannot contain exclamation marks") : (r = e.input.slice(u - 1, e.position + 1), c.test(r) || T(e, "named tag handle cannot contain such characters"), n = !0, u = e.position + 1)), o = e.input.charCodeAt(++e.position);
			a = e.input.slice(u, e.position), s.test(a) && T(e, "tag suffix cannot contain flow indicator characters");
		}
		a && !l.test(a) && T(e, "tag name cannot contain such characters: " + a);
		try {
			a = decodeURIComponent(a);
		} catch {
			T(e, "tag name is malformed: " + a);
		}
		return t ? e.tag = a : i.call(e.tagMap, r) ? e.tag = e.tagMap[r] + a : r === "!" ? e.tag = "!" + a : r === "!!" ? e.tag = "tag:yaml.org,2002:" + a : T(e, "undeclared tag handle \"" + r + "\""), !0;
	}
	k(ce, "readTagProperty");
	function le(e) {
		let t = e.input.charCodeAt(e.position);
		if (t !== 38) return !1;
		e.anchor !== null && T(e, "duplication of an anchor property"), t = e.input.charCodeAt(++e.position);
		let n = e.position;
		for (; t !== 0 && !p(t) && !m(t);) t = e.input.charCodeAt(++e.position);
		return e.position === n && T(e, "name of an anchor node must contain at least one character"), e.anchor = e.input.slice(n, e.position), !0;
	}
	k(le, "readAnchorProperty");
	function ue(e) {
		let t = e.input.charCodeAt(e.position);
		if (t !== 42) return !1;
		t = e.input.charCodeAt(++e.position);
		let n = e.position;
		for (; t !== 0 && !p(t) && !m(t);) t = e.input.charCodeAt(++e.position);
		e.position === n && T(e, "name of an alias node must contain at least one character");
		let r = e.input.slice(n, e.position);
		return i.call(e.anchorMap, r) || T(e, "unidentified alias \"" + r + "\""), e.result = e.anchorMap[r], R(e, !0, -1), !0;
	}
	k(ue, "readAlias");
	function de(e, t, n, r) {
		let i = M(e);
		return O(e), N(e, t), e.tag = null, e.anchor = null, e.kind = null, e.result = null, se(e, n, r) && e.kind === "mapping" ? (A(e), !0) : (j(e), N(e, i), !1);
	}
	k(de, "tryReadBlockMappingFromProperty");
	function V(e, t, n, r, a) {
		let o, s, c = 1, l = !1, u = !1, d = null, f, p, m;
		e.depth >= e.maxDepth && T(e, "nesting exceeded maxDepth (" + e.maxDepth + ")"), e.depth += 1, e.listener !== null && e.listener("open", e), e.tag = null, e.anchor = null, e.kind = null, e.result = null;
		let h = o = s = n === 4 || n === 3;
		if (r && R(e, !0, -1) && (l = !0, e.lineIndent > t ? c = 1 : e.lineIndent === t ? c = 0 : e.lineIndent < t && (c = -1)), c === 1) for (;;) {
			let n = e.input.charCodeAt(e.position), r = M(e);
			if (l && (n === 33 && e.tag !== null || n === 38 && e.anchor !== null) || !ce(e) && !le(e)) break;
			d === null && (d = r), R(e, !0, -1) ? (l = !0, s = h, e.lineIndent > t ? c = 1 : e.lineIndent === t ? c = 0 : e.lineIndent < t && (c = -1)) : s = !1;
		}
		if (s &&= l || a, c === 1 || n === 4) {
			if (p = n === 1 || n === 2 ? t : t + 1, m = e.position - e.lineStart, c === 1) {
				if (s && (oe(e, m) || se(e, m, p)) || ie(e, p)) u = !0;
				else {
					let t = e.input.charCodeAt(e.position);
					d !== null && h && !s && t !== 124 && t !== 62 && de(e, d, d.position - d.lineStart, p) || o && ae(e, p) || ne(e, p) || re(e, p) ? u = !0 : ue(e) ? (u = !0, (e.tag !== null || e.anchor !== null) && T(e, "alias node should not have any properties")) : te(e, p, n === 1) && (u = !0, e.tag === null && (e.tag = "?")), e.anchor !== null && D(e, e.anchor, e.result);
				}
			} else c === 0 && (u = s && oe(e, m));
		}
		if (e.tag === null) e.anchor !== null && D(e, e.anchor, e.result);
		else if (e.tag === "?") {
			e.result !== null && e.kind !== "scalar" && T(e, "unacceptable node kind for !<?> tag; it should be \"scalar\", not \"" + e.kind + "\"");
			for (let t = 0, n = e.implicitTypes.length; t < n; t += 1) if (f = e.implicitTypes[t], f.resolve(e.result)) {
				e.result = f.construct(e.result), e.tag = f.tag, e.anchor !== null && D(e, e.anchor, e.result);
				break;
			}
		} else if (e.tag !== "!") {
			if (i.call(e.typeMap[e.kind || "fallback"], e.tag)) f = e.typeMap[e.kind || "fallback"][e.tag];
			else {
				f = null;
				let t = e.typeMap.multi[e.kind || "fallback"];
				for (let n = 0, r = t.length; n < r; n += 1) if (e.tag.slice(0, t[n].tag.length) === t[n].tag) {
					f = t[n];
					break;
				}
			}
			f || T(e, "unknown tag !<" + e.tag + ">"), e.result !== null && f.kind !== e.kind && T(e, "unacceptable node kind for !<" + e.tag + "> tag; it should be \"" + f.kind + "\", not \"" + e.kind + "\""), f.resolve(e.result, e.tag) ? (e.result = f.construct(e.result, e.tag), e.anchor !== null && D(e, e.anchor, e.result)) : T(e, "cannot resolve a node with !<" + e.tag + "> explicit tag");
		}
		return e.listener !== null && e.listener("close", e), --e.depth, e.tag !== null || e.anchor !== null || u;
	}
	k(V, "composeNode");
	function fe(e) {
		let t = e.position, n = !1, r;
		for (e.version = null, e.checkLineBreaks = e.legacy, e.tagMap = /* @__PURE__ */ Object.create(null), e.anchorMap = /* @__PURE__ */ Object.create(null); (r = e.input.charCodeAt(e.position)) !== 0 && (R(e, !0, -1), r = e.input.charCodeAt(e.position), !(e.lineIndent > 0 || r !== 37));) {
			n = !0, r = e.input.charCodeAt(++e.position);
			let t = e.position;
			for (; r !== 0 && !p(r);) r = e.input.charCodeAt(++e.position);
			let a = e.input.slice(t, e.position), o = [];
			for (a.length < 1 && T(e, "directive name must not be less than one character in length"); r !== 0;) {
				for (; f(r);) r = e.input.charCodeAt(++e.position);
				if (r === 35) {
					do
						r = e.input.charCodeAt(++e.position);
					while (r !== 0 && !d(r));
					break;
				}
				if (d(r)) break;
				for (t = e.position; r !== 0 && !p(r);) r = e.input.charCodeAt(++e.position);
				o.push(e.input.slice(t, e.position));
			}
			r !== 0 && L(e), i.call(ee, a) ? ee[a](e, a, o) : E(e, "unknown document directive \"" + a + "\"");
		}
		R(e, !0, -1), e.lineIndent === 0 && e.input.charCodeAt(e.position) === 45 && e.input.charCodeAt(e.position + 1) === 45 && e.input.charCodeAt(e.position + 2) === 45 ? (e.position += 3, R(e, !0, -1)) : n && T(e, "directives end mark is expected"), V(e, e.lineIndent - 1, 4, !1, !0), R(e, !0, -1), e.checkLineBreaks && o.test(e.input.slice(t, e.position)) && E(e, "non-ASCII line breaks are interpreted as content"), e.documents.push(e.result), e.position === e.lineStart && z(e) ? e.input.charCodeAt(e.position) === 46 && (e.position += 3, R(e, !0, -1)) : e.position < e.length - 1 && T(e, "end of the stream or a document separator is expected");
	}
	k(fe, "readDocument");
	function pe(e, t) {
		e = String(e), t ||= {}, e.length !== 0 && (e.charCodeAt(e.length - 1) !== 10 && e.charCodeAt(e.length - 1) !== 13 && (e += "\n"), e.charCodeAt(0) === 65279 && (e = e.slice(1)));
		let n = new C(e, t), r = e.indexOf("\0");
		for (r !== -1 && (n.position = r, T(n, "null byte is not allowed in input")), n.input += "\0"; n.input.charCodeAt(n.position) === 32;) n.lineIndent += 1, n.position += 1;
		for (; n.position < n.length - 1;) fe(n);
		return n.documents;
	}
	k(pe, "loadDocuments");
	function me(e, t, n) {
		typeof t == "object" && t && n === void 0 && (n = t, t = null);
		let r = pe(e, n);
		if (typeof t != "function") return r;
		for (let e = 0, n = r.length; e < n; e += 1) t(r[e]);
	}
	k(me, "loadAll2");
	function he(e, n) {
		let r = pe(e, n);
		if (r.length !== 0) {
			if (r.length === 1) return r[0];
			throw new t("expected a single document in the stream, but found more");
		}
	}
	return k(he, "load2"), Ee.loadAll = me, Ee.load = he, Ee;
}
k(It, "requireLoader");
var Lt = {}, Rt;
function zt() {
	if (Rt) return Lt;
	Rt = 1;
	let e = W(), t = G(), n = Pt(), r = Object.prototype.toString, i = Object.prototype.hasOwnProperty, a = 65279, o = {};
	o[0] = "\\0", o[7] = "\\a", o[8] = "\\b", o[9] = "\\t", o[10] = "\\n", o[11] = "\\v", o[12] = "\\f", o[13] = "\\r", o[27] = "\\e", o[34] = "\\\"", o[92] = "\\\\", o[133] = "\\N", o[160] = "\\_", o[8232] = "\\L", o[8233] = "\\P";
	let s = [
		"y",
		"Y",
		"yes",
		"Yes",
		"YES",
		"on",
		"On",
		"ON",
		"n",
		"N",
		"no",
		"No",
		"NO",
		"off",
		"Off",
		"OFF"
	], c = /^[-+]?[0-9_]+(?::[0-9_]+)+(?:\.[0-9_]*)?$/;
	function l(e, t) {
		if (t === null) return {};
		let n = {}, r = Object.keys(t);
		for (let a = 0, o = r.length; a < o; a += 1) {
			let o = r[a], s = String(t[o]);
			o.slice(0, 2) === "!!" && (o = "tag:yaml.org,2002:" + o.slice(2));
			let c = e.compiledTypeMap.fallback[o];
			c && i.call(c.styleAliases, s) && (s = c.styleAliases[s]), n[o] = s;
		}
		return n;
	}
	k(l, "compileStyleMap");
	function u(n) {
		let r, i, a = n.toString(16).toUpperCase();
		if (n <= 255) r = "x", i = 2;
		else if (n <= 65535) r = "u", i = 4;
		else if (n <= 4294967295) r = "U", i = 8;
		else throw new t("code point within a string may not be greater than 0xFFFFFFFF");
		return "\\" + r + e.repeat("0", i - a.length) + a;
	}
	k(u, "encodeHex");
	function d(t) {
		this.schema = t.schema || n, this.indent = Math.max(1, t.indent || 2), this.noArrayIndent = t.noArrayIndent || !1, this.skipInvalid = t.skipInvalid || !1, this.flowLevel = e.isNothing(t.flowLevel) ? -1 : t.flowLevel, this.styleMap = l(this.schema, t.styles || null), this.sortKeys = t.sortKeys || !1, this.lineWidth = t.lineWidth || 80, this.noRefs = t.noRefs || !1, this.noCompatMode = t.noCompatMode || !1, this.condenseFlow = t.condenseFlow || !1, this.quotingType = t.quotingType === "\"" ? 2 : 1, this.forceQuotes = t.forceQuotes || !1, this.replacer = typeof t.replacer == "function" ? t.replacer : null, this.implicitTypes = this.schema.compiledImplicit, this.explicitTypes = this.schema.compiledExplicit, this.tag = null, this.result = "", this.duplicates = [], this.usedDuplicates = null;
	}
	k(d, "State");
	function f(t, n) {
		let r = e.repeat(" ", n), i = 0, a = "", o = t.length;
		for (; i < o;) {
			let e, n = t.indexOf("\n", i);
			n === -1 ? (e = t.slice(i), i = o) : (e = t.slice(i, n + 1), i = n + 1), e.length && e !== "\n" && (a += r), a += e;
		}
		return a;
	}
	k(f, "indentString");
	function p(t, n) {
		return "\n" + e.repeat(" ", t.indent * n);
	}
	k(p, "generateNextLine");
	function m(e, t) {
		for (let n = 0, r = e.implicitTypes.length; n < r; n += 1) if (e.implicitTypes[n].resolve(t)) return !0;
		return !1;
	}
	k(m, "testImplicitResolving");
	function h(e) {
		return e === 32 || e === 9;
	}
	k(h, "isWhitespace");
	function g(e) {
		return e >= 32 && e <= 126 || e >= 161 && e <= 55295 && e !== 8232 && e !== 8233 || e >= 57344 && e <= 65533 && e !== a || e >= 65536 && e <= 1114111;
	}
	k(g, "isPrintable");
	function _(e) {
		return g(e) && e !== a && e !== 13 && e !== 10;
	}
	k(_, "isNsCharOrWhitespace");
	function v(e, t, n) {
		let r = _(e), i = r && !h(e);
		return (n ? r : r && e !== 44 && e !== 91 && e !== 93 && e !== 123 && e !== 125) && e !== 35 && !(t === 58 && !i) || _(t) && !h(t) && e === 35 || t === 58 && i;
	}
	k(v, "isPlainSafe");
	function y(e) {
		return g(e) && e !== a && !h(e) && e !== 45 && e !== 63 && e !== 58 && e !== 44 && e !== 91 && e !== 93 && e !== 123 && e !== 125 && e !== 35 && e !== 38 && e !== 42 && e !== 33 && e !== 124 && e !== 61 && e !== 62 && e !== 39 && e !== 34 && e !== 37 && e !== 64 && e !== 96;
	}
	k(y, "isPlainSafeFirst");
	function b(e) {
		return !h(e) && e !== 58;
	}
	k(b, "isPlainSafeLast");
	function x(e, t) {
		let n = e.charCodeAt(t), r;
		return n >= 55296 && n <= 56319 && t + 1 < e.length && (r = e.charCodeAt(t + 1), r >= 56320 && r <= 57343) ? (n - 55296) * 1024 + r - 56320 + 65536 : n;
	}
	k(x, "codePointAt");
	function S(e) {
		return /^\n* /.test(e);
	}
	k(S, "needIndentIndicator");
	function C(e, t, n, r, i, a, o, s) {
		let c, l = 0, u = null, d = !1, f = !1, p = r !== -1, m = -1, h = y(x(e, 0)) && b(x(e, e.length - 1));
		if (t || o) for (c = 0; c < e.length; l >= 65536 ? c += 2 : c++) {
			if (l = x(e, c), !g(l)) return 5;
			h &&= v(l, u, s), u = l;
		}
		else {
			for (c = 0; c < e.length; l >= 65536 ? c += 2 : c++) {
				if (l = x(e, c), l === 10) d = !0, p && (f ||= c - m - 1 > r && e[m + 1] !== " ", m = c);
				else if (!g(l)) return 5;
				h &&= v(l, u, s), u = l;
			}
			f ||= p && c - m - 1 > r && e[m + 1] !== " ";
		}
		return !d && !f ? h && !o && !i(e) ? 1 : a === 2 ? 5 : 2 : n > 9 && S(e) ? 5 : o ? a === 2 ? 5 : 2 : f ? 4 : 3;
	}
	k(C, "chooseScalarStyle");
	function w(e, n, r, i, a) {
		e.dump = (function() {
			if (n.length === 0) return e.quotingType === 2 ? "\"\"" : "''";
			if (!e.noCompatMode && (s.indexOf(n) !== -1 || c.test(n))) return e.quotingType === 2 ? "\"" + n + "\"" : "'" + n + "'";
			let o = e.indent * Math.max(1, r), l = e.lineWidth === -1 ? -1 : Math.max(Math.min(e.lineWidth, 40), e.lineWidth - o), u = i || e.flowLevel > -1 && r >= e.flowLevel;
			function d(t) {
				return m(e, t);
			}
			switch (k(d, "testAmbiguity"), C(n, u, e.indent, l, d, e.quotingType, e.forceQuotes && !i, a)) {
				case 1: return n;
				case 2: return "'" + n.replace(/'/g, "''") + "'";
				case 3: return "|" + T(n, e.indent) + E(f(n, o));
				case 4: return ">" + T(n, e.indent) + E(f(D(n, l), o));
				case 5: return "\"" + A(n) + "\"";
				default: throw new t("impossible error: invalid scalar style");
			}
		})();
	}
	k(w, "writeScalar");
	function T(e, t) {
		let n = S(e) ? String(t) : "", r = e[e.length - 1] === "\n";
		return n + (r && (e[e.length - 2] === "\n" || e === "\n") ? "+" : r ? "" : "-") + "\n";
	}
	k(T, "blockHeader");
	function E(e) {
		return e[e.length - 1] === "\n" ? e.slice(0, -1) : e;
	}
	k(E, "dropEndingNewline");
	function D(e, t) {
		let n = /(\n+)([^\n]*)/g, r = (function() {
			let r = e.indexOf("\n");
			return r = r === -1 ? e.length : r, n.lastIndex = r, O(e.slice(0, r), t);
		})(), i = e[0] === "\n" || e[0] === " ", a, o;
		for (; o = n.exec(e);) {
			let e = o[1], n = o[2];
			a = n[0] === " ", r += e + (!i && !a && n !== "" ? "\n" : "") + O(n, t), i = a;
		}
		return r;
	}
	k(D, "foldString");
	function O(e, t) {
		if (e === "" || e[0] === " ") return e;
		let n = / [^ ]/g, r, i = 0, a, o = 0, s = 0, c = "";
		for (; r = n.exec(e);) s = r.index, s - i > t && (a = o > i ? o : s, c += "\n" + e.slice(i, a), i = a + 1), o = s;
		return c += "\n", e.length - i > t && o > i ? c += e.slice(i, o) + "\n" + e.slice(o + 1) : c += e.slice(i), c.slice(1);
	}
	k(O, "foldLine");
	function A(e) {
		let t = "", n = 0;
		for (let r = 0; r < e.length; n >= 65536 ? r += 2 : r++) {
			n = x(e, r);
			let i = o[n];
			!i && g(n) ? (t += e[r], n >= 65536 && (t += e[r + 1])) : t += i || u(n);
		}
		return t;
	}
	k(A, "escapeString");
	function j(e, t, n) {
		let r = "", i = e.tag;
		for (let i = 0, a = n.length; i < a; i += 1) {
			let a = n[i];
			e.replacer && (a = e.replacer.call(n, String(i), a)), (F(e, t, a, !1, !1) || a === void 0 && F(e, t, null, !1, !1)) && (r !== "" && (r += "," + (e.condenseFlow ? "" : " ")), r += e.dump);
		}
		e.tag = i, e.dump = "[" + r + "]";
	}
	k(j, "writeFlowSequence");
	function M(e, t, n, r) {
		let i = "", a = e.tag;
		for (let a = 0, o = n.length; a < o; a += 1) {
			let o = n[a];
			e.replacer && (o = e.replacer.call(n, String(a), o)), (F(e, t + 1, o, !0, !0, !1, !0) || o === void 0 && F(e, t + 1, null, !0, !0, !1, !0)) && (r && i === "" || (i += p(e, t)), e.dump && e.dump.charCodeAt(0) === 10 ? i += "-" : i += "- ", i += e.dump);
		}
		e.tag = a, e.dump = i || "[]";
	}
	k(M, "writeBlockSequence");
	function N(e, t, n) {
		let r = "", i = e.tag, a = Object.keys(n);
		for (let i = 0, o = a.length; i < o; i += 1) {
			let o = "";
			r !== "" && (o += ", "), e.condenseFlow && (o += "\"");
			let s = a[i], c = n[s];
			e.replacer && (c = e.replacer.call(n, s, c)), F(e, t, s, !1, !1) && (e.dump.length > 1024 && (o += "? "), o += e.dump + (e.condenseFlow ? "\"" : "") + ":" + (e.condenseFlow ? "" : " "), F(e, t, c, !1, !1) && (o += e.dump, r += o));
		}
		e.tag = i, e.dump = "{" + r + "}";
	}
	k(N, "writeFlowMapping");
	function ee(e, n, r, i) {
		let a = "", o = e.tag, s = Object.keys(r);
		if (e.sortKeys === !0) s.sort();
		else if (typeof e.sortKeys == "function") s.sort(e.sortKeys);
		else if (e.sortKeys) throw new t("sortKeys must be a boolean or a function");
		for (let t = 0, o = s.length; t < o; t += 1) {
			let o = "";
			i && a === "" || (o += p(e, n));
			let c = s[t], l = r[c];
			if (e.replacer && (l = e.replacer.call(r, c, l)), !F(e, n + 1, c, !0, !0, !0)) continue;
			let u = e.tag !== null && e.tag !== "?" || e.dump && e.dump.length > 1024;
			u && (e.dump && e.dump.charCodeAt(0) === 10 ? o += "?" : o += "? "), o += e.dump, u && (o += p(e, n)), F(e, n + 1, l, !0, u) && (e.dump && e.dump.charCodeAt(0) === 10 ? o += ":" : o += ": ", o += e.dump, a += o);
		}
		e.tag = o, e.dump = a || "{}";
	}
	k(ee, "writeBlockMapping");
	function P(e, n, a) {
		let o = a ? e.explicitTypes : e.implicitTypes;
		for (let s = 0, c = o.length; s < c; s += 1) {
			let c = o[s];
			if ((c.instanceOf || c.predicate) && (!c.instanceOf || typeof n == "object" && n instanceof c.instanceOf) && (!c.predicate || c.predicate(n))) {
				if (e.tag = a ? c.multi && c.representName ? c.representName(n) : c.tag : "?", c.represent) {
					let a = e.styleMap[c.tag] || c.defaultStyle, o;
					if (r.call(c.represent) === "[object Function]") o = c.represent(n, a);
					else if (i.call(c.represent, a)) o = c.represent[a](n, a);
					else throw new t("!<" + c.tag + "> tag resolver accepts not \"" + a + "\" style");
					e.dump = o;
				}
				return !0;
			}
		}
		return !1;
	}
	k(P, "detectType");
	function F(e, n, i, a, o, s, c) {
		e.tag = null, e.dump = i, P(e, i, !1) || P(e, i, !0);
		let l = r.call(e.dump), u = a;
		a &&= e.flowLevel < 0 || e.flowLevel > n;
		let d = l === "[object Object]" || l === "[object Array]", f, p;
		if (d && (f = e.duplicates.indexOf(i), p = f !== -1), (e.tag !== null && e.tag !== "?" || p || e.indent !== 2 && n > 0) && (o = !1), p && e.usedDuplicates[f]) e.dump = "*ref_" + f;
		else {
			if (d && p && !e.usedDuplicates[f] && (e.usedDuplicates[f] = !0), l === "[object Object]") a && Object.keys(e.dump).length !== 0 ? (ee(e, n, e.dump, o), p && (e.dump = "&ref_" + f + e.dump)) : (N(e, n, e.dump), p && (e.dump = "&ref_" + f + " " + e.dump));
			else if (l === "[object Array]") a && e.dump.length !== 0 ? (e.noArrayIndent && !c && n > 0 ? M(e, n - 1, e.dump, o) : M(e, n, e.dump, o), p && (e.dump = "&ref_" + f + e.dump)) : (j(e, n, e.dump), p && (e.dump = "&ref_" + f + " " + e.dump));
			else if (l === "[object String]") e.tag !== "?" && w(e, e.dump, n, s, u);
			else if (l === "[object Undefined]") return !1;
			else {
				if (e.skipInvalid) return !1;
				throw new t("unacceptable kind of an object to dump " + l);
			}
			if (e.tag !== null && e.tag !== "?") {
				let t = encodeURI(e.tag[0] === "!" ? e.tag.slice(1) : e.tag).replace(/!/g, "%21");
				t = e.tag[0] === "!" ? "!" + t : t.slice(0, 18) === "tag:yaml.org,2002:" ? "!!" + t.slice(18) : "!<" + t + ">", e.dump = t + " " + e.dump;
			}
		}
		return !0;
	}
	k(F, "writeNode");
	function I(e, t) {
		let n = [], r = [];
		L(e, n, r);
		let i = r.length;
		for (let e = 0; e < i; e += 1) t.duplicates.push(n[r[e]]);
		t.usedDuplicates = Array(i);
	}
	k(I, "getDuplicateReferences");
	function L(e, t, n) {
		if (typeof e == "object" && e) {
			let r = t.indexOf(e);
			if (r !== -1) n.indexOf(r) === -1 && n.push(r);
			else if (t.push(e), Array.isArray(e)) for (let r = 0, i = e.length; r < i; r += 1) L(e[r], t, n);
			else {
				let r = Object.keys(e);
				for (let i = 0, a = r.length; i < a; i += 1) L(e[r[i]], t, n);
			}
		}
	}
	k(L, "inspectNode");
	function R(e, t) {
		t ||= {};
		let n = new d(t);
		n.noRefs || I(e, n);
		let r = e;
		return n.replacer && (r = n.replacer.call({ "": r }, "", r)), F(n, 0, r, !0, !0) ? n.dump + "\n" : "";
	}
	return k(R, "dump2"), Lt.dump = R, Lt;
}
k(zt, "requireDumper");
var Bt;
function Vt() {
	if (Bt) return H;
	Bt = 1;
	let e = It(), t = zt();
	function n(e, t) {
		return function() {
			throw Error("Function yaml." + e + " is removed in js-yaml 4. Use yaml." + t + " instead, which is now safe by default.");
		};
	}
	return k(n, "renamed"), H.Type = K(), H.Schema = Le(), H.FAILSAFE_SCHEMA = Ye(), H.JSON_SCHEMA = ut(), H.CORE_SCHEMA = pt(), H.DEFAULT_SCHEMA = Pt(), H.load = e.load, H.loadAll = e.loadAll, H.dump = t.dump, H.YAMLException = G(), H.types = {
		binary: St(),
		float: st(),
		map: Ke(),
		null: Qe(),
		pairs: Ot(),
		set: jt(),
		timestamp: gt(),
		bool: tt(),
		int: it(),
		merge: yt(),
		omap: Tt(),
		seq: Ue(),
		str: Be()
	}, H.safeLoad = n("safeLoad", "load"), H.safeLoadAll = n("safeLoadAll", "loadAll"), H.safeDump = n("safeDump", "dump"), H;
}
k(Vt, "requireJsYaml");
var { Type: Ht, Schema: Ut, FAILSAFE_SCHEMA: Wt, JSON_SCHEMA: Gt, CORE_SCHEMA: Kt, DEFAULT_SCHEMA: qt, load: Jt, loadAll: Yt, dump: Xt, YAMLException: Zt, types: Qt, safeLoad: $t, safeLoadAll: en, safeDump: tn } = /* @__PURE__ */ Te(Vt()), nn = "comm", rn = "rule", an = "decl", on = "@import", sn = "@namespace", cn = "@keyframes", ln = "@layer", un = Math.abs, dn = String.fromCharCode;
function fn(e) {
	return e.trim();
}
function pn(e, t, n) {
	return e.replace(t, n);
}
function mn(e, t) {
	return e.charCodeAt(t) | 0;
}
function hn(e, t, n) {
	return e.slice(t, n);
}
function q(e) {
	return e.length;
}
function gn(e) {
	return e.length;
}
function _n(e, t) {
	return t.push(e), e;
}
// node_modules/.pnpm/stylis@4.4.0/node_modules/stylis/src/Tokenizer.js
var vn = 1, yn = 1, bn = 0, J = 0, Y = 0, xn = "";
function Sn(e, t, n, r, i, a, o, s) {
	return {
		value: e,
		root: t,
		parent: n,
		type: r,
		props: i,
		children: a,
		line: vn,
		column: yn,
		length: o,
		return: "",
		siblings: s
	};
}
function Cn() {
	return Y;
}
function wn() {
	return Y = J > 0 ? mn(xn, --J) : 0, yn--, Y === 10 && (yn = 1, vn--), Y;
}
function X() {
	return Y = J < bn ? mn(xn, J++) : 0, yn++, Y === 10 && (yn = 1, vn++), Y;
}
function Z() {
	return mn(xn, J);
}
function Tn() {
	return J;
}
function En(e, t) {
	return hn(xn, e, t);
}
function Dn(e) {
	switch (e) {
		case 0:
		case 9:
		case 10:
		case 13:
		case 32: return 5;
		case 33:
		case 43:
		case 44:
		case 47:
		case 62:
		case 64:
		case 126:
		case 59:
		case 123:
		case 125: return 4;
		case 58: return 3;
		case 34:
		case 39:
		case 40:
		case 91: return 2;
		case 41:
		case 93: return 1;
	}
	return 0;
}
function On(e) {
	return vn = yn = 1, bn = q(xn = e), J = 0, [];
}
function kn(e) {
	return xn = "", e;
}
function An(e) {
	return fn(En(J - 1, Nn(e === 91 ? e + 2 : e === 40 ? e + 1 : e)));
}
function jn(e) {
	for (; (Y = Z()) && Y < 33;) X();
	return Dn(e) > 2 || Dn(Y) > 3 ? "" : " ";
}
function Mn(e, t) {
	for (; --t && X() && !(Y < 48 || Y > 102 || Y > 57 && Y < 65 || Y > 70 && Y < 97););
	return En(e, Tn() + (t < 6 && Z() == 32 && X() == 32));
}
function Nn(e) {
	for (; X();) switch (Y) {
		case e: return J;
		case 34:
		case 39:
			e !== 34 && e !== 39 && Nn(Y);
			break;
		case 40:
			e === 41 && Nn(e);
			break;
		case 92: X();
	}
	return J;
}
function Pn(e, t) {
	for (; X() && e + Y !== 57 && (e + Y !== 84 || Z() !== 47););
	return "/*" + En(t, J - 1) + "*" + dn(e === 47 ? e : X());
}
function Fn(e) {
	for (; !Dn(Z());) X();
	return En(e, J);
}
// node_modules/.pnpm/stylis@4.4.0/node_modules/stylis/src/Parser.js
function In(e) {
	return kn(Ln("", null, null, null, [""], e = On(e), 0, [0], e));
}
function Ln(e, t, n, r, i, a, o, s, c) {
	for (var l = 0, u = 0, d = o, f = 0, p = 0, m = 0, h = 1, g = 1, _ = 1, v = 0, y = 0, b = "", x = i, S = a, C = r, w = b; g;) switch (m = y, y = X()) {
		case 40:
			m != 108 && mn(w, d - 1) == 58 ? (v++, w += "(") : w += An(y);
			break;
		case 41:
			v--, w += ")";
			break;
		case 34:
		case 39:
		case 91:
			w += An(y);
			break;
		case 9:
		case 10:
		case 13:
		case 32:
			if (v > 0) {
				w += dn(y);
				break;
			}
			w += jn(m);
			break;
		case 92:
			w += Mn(Tn() - 1, 7);
			continue;
		case 47:
			switch (Z()) {
				case 42:
				case 47:
					_n(zn(Pn(X(), Tn()), t, n, c), c), (Dn(m || 1) == 5 || Dn(Z() || 1) == 5) && q(w) && hn(w, -1, void 0) !== " " && (w += " ");
					break;
				default: w += "/";
			}
			break;
		case 123 * h: s[l++] = q(w) * _;
		case 125 * h:
		case 59:
		case 0:
			if (v > 0 && y) {
				w += dn(y);
				break;
			}
			switch (y) {
				case 0:
				case 125: g = 0;
				case 59 + u:
					_ == -1 && (w = pn(w, /\f/g, "")), p > 0 && (q(w) - d || h === 0) && _n(p > 32 ? Bn(w + ";", r, n, d - 1, c) : Bn(pn(w, " ", "") + ";", r, n, d - 2, c), c);
					break;
				case 59: w += ";";
				default: if (_n(C = Rn(w, t, n, l, u, i, s, b, x = [], S = [], d, a), a), y === 123) {
					if (u === 0) Ln(w, t, C, C, x, a, d, s, S);
					else {
						switch (f) {
							case 99: if (mn(w, 3) === 110) break;
							case 108: if (mn(w, 2) === 97) break;
							default: u = 0;
							case 100:
							case 109:
							case 115:
						}
						u ? Ln(e, C, C, r && _n(Rn(e, C, C, 0, 0, i, s, b, i, x = [], d, S), S), i, S, d, s, r ? x : S) : Ln(w, C, C, C, [""], S, 0, s, S);
					}
				}
			}
			l = u = p = 0, h = _ = 1, b = w = "", d = o;
			break;
		case 58: d = 1 + q(w), p = m;
		default:
			if (h < 1) {
				if (y == 123) --h;
				else if (y == 125 && h++ == 0 && wn() == 125) continue;
			}
			switch (w += dn(y), y * h) {
				case 38:
					_ = u > 0 ? 1 : (w += "\f", -1);
					break;
				case 44:
					if (v > 0) break;
					s[l++] = (q(w) - 1) * _, _ = 1;
					break;
				case 64:
					Z() === 45 && (w += An(X())), f = Z(), u = d = q(b = w += Fn(Tn())), y++;
					break;
				case 45: m === 45 && q(w) == 2 && (h = 0);
			}
	}
	return a;
}
function Rn(e, t, n, r, i, a, o, s, c, l, u, d) {
	for (var f = i - 1, p = i === 0 ? a : [""], m = gn(p), h = 0, g = 0, _ = 0; h < r; ++h) for (var v = 0, y = hn(e, f + 1, f = un(g = o[h])), b = e; v < m; ++v) (b = fn(g > 0 ? p[v] + " " + y : pn(y, /&\f/g, p[v]))) && (c[_++] = b);
	return Sn(e, t, n, i === 0 ? rn : s, c, l, u, d);
}
function zn(e, t, n, r) {
	return Sn(e, t, n, nn, dn(Cn()), hn(e, 2, -2), 0, r);
}
function Bn(e, t, n, r, i) {
	return Sn(e, t, n, an, hn(e, 0, r), hn(e, r + 1, -1), r, i);
}
// node_modules/.pnpm/stylis@4.4.0/node_modules/stylis/src/Serializer.js
function Vn(e, t) {
	for (var n = "", r = 0; r < e.length; r++) n += t(e[r], r, e, t) || "";
	return n;
}
function Hn(e, t, n, r) {
	switch (e.type) {
		case ln: if (e.children.length) break;
		case on:
		case sn:
		case an: return e.return = e.return || e.value;
		case nn: return "";
		case cn: return e.return = e.value + "{" + Vn(e.children, r) + "}";
		case rn: if (!q(e.value = e.props.join(","))) return "";
	}
	return q(n = Vn(e.children, r)) ? e.return = e.value + "{" + n + "}" : "";
}
// node_modules/.pnpm/stylis@4.4.0/node_modules/stylis/src/Middleware.js
function Un(e) {
	var t = gn(e);
	return function(n, r, i, a) {
		for (var o = "", s = 0; s < t; s++) o += e[s](n, r, i, a) || "";
		return o;
	};
}
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/mermaid.core.mjs
var Wn = "c4", Gn = {
	id: Wn,
	detector: /* @__PURE__ */ k((e) => /^\s*C4Context|C4Container|C4Component|C4Dynamic|C4Deployment/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./c4Diagram-XDPPPE54-BP23Lo4W.js");
		return {
			id: Wn,
			diagram: e
		};
	}, "loader")
}, Kn = "flowchart-v2", qn = {
	id: Kn,
	detector: /* @__PURE__ */ k((e) => /^\s*(graph|flowchart)/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./flowDiagram-IUGLLWBU-B7SqZYPJ.js");
		return {
			id: Kn,
			diagram: e
		};
	}, "loader")
}, Jn = "agentflow", Yn = {
	id: Jn,
	detector: /* @__PURE__ */ k((e) => /^\s*agentflow-beta\b/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-TYUNFSNR-BIIsqC4x.js");
		return {
			id: Jn,
			diagram: e
		};
	}, "loader")
}, Xn = "swimlane", Zn = {
	id: Xn,
	detector: /* @__PURE__ */ k((e) => /^\s*swimlane-beta\b/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./swimlanesDiagram-AYQRV4AK-5Qo8y-6y.js");
		return {
			id: Xn,
			diagram: e
		};
	}, "loader")
}, Qn = "er", $n = {
	id: Qn,
	detector: /* @__PURE__ */ k((e) => /^\s*erDiagram/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./erDiagram-KYQIGQAA-C8xGeEOZ.js");
		return {
			id: Qn,
			diagram: e
		};
	}, "loader")
}, er = "gitGraph", tr = {
	id: er,
	detector: /* @__PURE__ */ k((e) => /^\s*gitGraph/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./gitGraphDiagram-PDYL7XHY-CsP-ThXc.js");
		return {
			id: er,
			diagram: e
		};
	}, "loader")
}, nr = "gantt", rr = {
	id: nr,
	detector: /* @__PURE__ */ k((e) => /^\s*gantt/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./ganttDiagram-R7GPNTJY-hxtLySjt.js");
		return {
			id: nr,
			diagram: e
		};
	}, "loader")
}, ir = "info", ar = {
	id: ir,
	detector: /* @__PURE__ */ k((e) => /^\s*info/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./infoDiagram-MVDRKAX5-BN35lJGM.js");
		return {
			id: ir,
			diagram: e
		};
	}, "loader")
}, or = "pie", sr = {
	id: or,
	detector: /* @__PURE__ */ k((e) => /^\s*pie/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./pieDiagram-GNMPY74T-Cf31pBpx.js");
		return {
			id: or,
			diagram: e
		};
	}, "loader")
}, cr = "quadrantChart", lr = {
	id: cr,
	detector: /* @__PURE__ */ k((e) => /^\s*quadrantChart/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./quadrantDiagram-D57K4MC3-CmtuDjJJ.js");
		return {
			id: cr,
			diagram: e
		};
	}, "loader")
}, ur = "xychart", dr = {
	id: ur,
	detector: /* @__PURE__ */ k((e) => /^\s*xychart(-beta)?/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./xychartDiagram-4URNHDU4-D0QE9JQM.js");
		return {
			id: ur,
			diagram: e
		};
	}, "loader")
}, fr = "requirement", pr = {
	id: fr,
	detector: /* @__PURE__ */ k((e) => /^\s*requirement(Diagram)?/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./requirementDiagram-EQYSW5RA-Cye6bCAO.js");
		return {
			id: fr,
			diagram: e
		};
	}, "loader")
}, mr = "sequence", hr = {
	id: mr,
	detector: /* @__PURE__ */ k((e) => /^\s*sequenceDiagram/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./sequenceDiagram-BLEHNHQU-BKVZgpb2.js");
		return {
			id: mr,
			diagram: e
		};
	}, "loader")
}, gr = "classDiagram", _r = {
	id: gr,
	detector: /* @__PURE__ */ k((e) => /^\s*classDiagram/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./classDiagram-v2-4MYWR3UA-COZZbmzU.js");
		return {
			id: gr,
			diagram: e
		};
	}, "loader")
}, vr = "stateDiagram", yr = {
	id: vr,
	detector: /* @__PURE__ */ k((e) => /^\s*stateDiagram/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./stateDiagram-v2-A6IESKWZ-CL9HC7un.js");
		return {
			id: vr,
			diagram: e
		};
	}, "loader")
}, br = "journey", xr = {
	id: br,
	detector: /* @__PURE__ */ k((e) => /^\s*journey/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./journeyDiagram-BOSHW7O7-DZ_Pqavm.js");
		return {
			id: br,
			diagram: e
		};
	}, "loader")
}, Sr = 4, Cr = 75, wr = /* @__PURE__ */ k((e) => {
	let t = [];
	for (let n of e.split(/\s+/)) {
		let e = [...n];
		for (; e.length > Cr;) t.push(e.slice(0, Cr).join("")), e = e.slice(Cr);
		e.length > 0 && t.push(e.join(""));
	}
	let n = [], r = "";
	for (let e of t) {
		let t = r ? `${r} ${e}` : e;
		[...t].length > Cr ? (n.push(r), r = e) : r = t;
	}
	if (r && n.push(r), n.length > Sr) {
		n.length = Sr;
		let e = n[Sr - 1] ?? "";
		n[Sr - 1] = `${[...e].slice(0, Cr - 3).join("")}...`;
	}
	return n;
}, "wrapErrorMessage"), Tr = { draw: /* @__PURE__ */ k((e, t, n, r) => {
	j.debug("rendering svg for syntax error\n");
	let i = me(t), a = i.append("g"), o = wr(r?.db.getErrorMessage?.() ?? ""), s = o.length > 0 ? 500 + o.length * 56 : 512;
	i.attr("viewBox", `0 0 2412 ${s}`), m(i, 100, s, !0), a.append("path").attr("class", "error-icon").attr("d", "m411.313,123.313c6.25-6.25 6.25-16.375 0-22.625s-16.375-6.25-22.625,0l-32,32-9.375,9.375-20.688-20.688c-12.484-12.5-32.766-12.5-45.25,0l-16,16c-1.261,1.261-2.304,2.648-3.31,4.051-21.739-8.561-45.324-13.426-70.065-13.426-105.867,0-192,86.133-192,192s86.133,192 192,192 192-86.133 192-192c0-24.741-4.864-48.327-13.426-70.065 1.402-1.007 2.79-2.049 4.051-3.31l16-16c12.5-12.492 12.5-32.758 0-45.25l-20.688-20.688 9.375-9.375 32.001-31.999zm-219.313,100.687c-52.938,0-96,43.063-96,96 0,8.836-7.164,16-16,16s-16-7.164-16-16c0-70.578 57.422-128 128-128 8.836,0 16,7.164 16,16s-7.164,16-16,16z"), a.append("path").attr("class", "error-icon").attr("d", "m459.02,148.98c-6.25-6.25-16.375-6.25-22.625,0s-6.25,16.375 0,22.625l16,16c3.125,3.125 7.219,4.688 11.313,4.688 4.094,0 8.188-1.563 11.313-4.688 6.25-6.25 6.25-16.375 0-22.625l-16.001-16z"), a.append("path").attr("class", "error-icon").attr("d", "m340.395,75.605c3.125,3.125 7.219,4.688 11.313,4.688 4.094,0 8.188-1.563 11.313-4.688 6.25-6.25 6.25-16.375 0-22.625l-16-16c-6.25-6.25-16.375-6.25-22.625,0s-6.25,16.375 0,22.625l15.999,16z"), a.append("path").attr("class", "error-icon").attr("d", "m400,64c8.844,0 16-7.164 16-16v-32c0-8.836-7.156-16-16-16-8.844,0-16,7.164-16,16v32c0,8.836 7.156,16 16,16z"), a.append("path").attr("class", "error-icon").attr("d", "m496,96.586h-32c-8.844,0-16,7.164-16,16 0,8.836 7.156,16 16,16h32c8.844,0 16-7.164 16-16 0-8.836-7.156-16-16-16z"), a.append("path").attr("class", "error-icon").attr("d", "m436.98,75.605c3.125,3.125 7.219,4.688 11.313,4.688 4.094,0 8.188-1.563 11.313-4.688l32-32c6.25-6.25 6.25-16.375 0-22.625s-16.375-6.25-22.625,0l-32,32c-6.251,6.25-6.251,16.375-0.001,22.625z"), a.append("text").attr("class", "error-text").attr("x", 1440).attr("y", 250).attr("font-size", "150px").style("text-anchor", "middle").text("Syntax error in text"), a.append("text").attr("class", "error-text").attr("x", 1250).attr("y", 400).attr("font-size", "100px").style("text-anchor", "middle").text(`mermaid version ${n}`), o.forEach((e, t) => {
		a.append("text").attr("class", "error-text").attr("x", 1440).attr("y", 510 + t * 56).attr("font-size", "42px").style("text-anchor", "middle").text(e);
	});
}, "draw") }, Er = Tr, Dr, Or = {
	db: {
		clear() {
			Dr = void 0;
		},
		setErrorMessage(e) {
			Dr = e;
		},
		getErrorMessage() {
			return Dr;
		}
	},
	renderer: Tr,
	parser: { parse: /* @__PURE__ */ k(() => {}, "parse") }
}, kr = "flowchart-elk", Ar = {
	id: kr,
	detector: /* @__PURE__ */ k((e, t = {}) => /^\s*flowchart-elk/.test(e) ? (t.layout = "elk", !0) : !1, "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./flowDiagram-IUGLLWBU-B7SqZYPJ.js");
		return {
			id: kr,
			diagram: e
		};
	}, "loader")
}, jr = "timeline", Mr = {
	id: jr,
	detector: /* @__PURE__ */ k((e) => /^\s*timeline/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./timeline-definition-JXYKGLGB-CIB7QJJv.js");
		return {
			id: jr,
			diagram: e
		};
	}, "loader")
}, Nr = "mindmap", Pr = {
	id: Nr,
	detector: /* @__PURE__ */ k((e) => /^\s*mindmap/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./mindmap-definition-6OY57UG3-CI35sDEm.js");
		return {
			id: Nr,
			diagram: e
		};
	}, "loader")
}, Fr = "kanban", Ir = {
	id: Fr,
	detector: /* @__PURE__ */ k((e) => /^\s*kanban/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./kanban-definition-FNSA4HC5-DZt54s6M.js");
		return {
			id: Fr,
			diagram: e
		};
	}, "loader")
}, Lr = "sankey", Rr = {
	id: Lr,
	detector: /* @__PURE__ */ k((e) => /^\s*sankey(-beta)?/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./sankeyDiagram-SY24JHO5-D8UozYsq.js");
		return {
			id: Lr,
			diagram: e
		};
	}, "loader")
}, zr = "packet", Br = {
	id: zr,
	detector: /* @__PURE__ */ k((e) => /^\s*packet(-beta)?/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-I5XIIV2D-DYPEtZXH.js");
		return {
			id: zr,
			diagram: e
		};
	}, "loader")
}, Vr = "radar", Hr = {
	id: Vr,
	detector: /* @__PURE__ */ k((e) => /^\s*radar-beta/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-RJA3I4VV-DbmM2zyg.js");
		return {
			id: Vr,
			diagram: e
		};
	}, "loader")
}, Ur = "block", Wr = {
	id: Ur,
	detector: /* @__PURE__ */ k((e) => /^\s*block(-beta)?/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./blockDiagram-RJAE7QA3-C1I4No2T.js");
		return {
			id: Ur,
			diagram: e
		};
	}, "loader")
}, Gr = "treeView", Kr = {
	id: Gr,
	detector: /* @__PURE__ */ k((e) => /^\s*treeView-beta/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-ODIL2UCW-CEt8OXSi.js");
		return {
			id: Gr,
			diagram: e
		};
	}, "loader")
}, qr = "architecture", Jr = {
	id: qr,
	detector: /* @__PURE__ */ k((e) => /^\s*architecture/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./architectureDiagram-ZHVYKT53-CPp7MC7K.js");
		return {
			id: qr,
			diagram: e
		};
	}, "loader")
}, Yr = "eventmodeling", Xr = {
	id: Yr,
	detector: /* @__PURE__ */ k((e) => /^\s*eventmodeling/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-PFBEJUXE-BDBvdulP.js");
		return {
			id: Yr,
			diagram: e
		};
	}, "loader")
}, Zr = "ishikawa", Qr = {
	id: Zr,
	detector: /* @__PURE__ */ k((e) => /^\s*ishikawa(-beta)?\b/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./ishikawaDiagram-HPEHV2GE-BTwVCCYp.js");
		return {
			id: Zr,
			diagram: e
		};
	}, "loader")
}, $r = "venn", ei = {
	id: $r,
	detector: /* @__PURE__ */ k((e) => /^\s*venn-beta/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./vennDiagram-Q3Y3IDCW-C4gkrsji.js");
		return {
			id: $r,
			diagram: e
		};
	}, "loader")
}, ti = "treemap", ni = {
	id: ti,
	detector: /* @__PURE__ */ k((e) => /^\s*treemap/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./diagram-CBK3MWTC-xab6Omws.js");
		return {
			id: ti,
			diagram: e
		};
	}, "loader")
}, ri = "usecase", ii = {
	id: ri,
	detector: /* @__PURE__ */ k((e) => /^\s*usecase-beta(?:\s|$)/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./usecaseDiagram-WPOLBNNX-WjCXMTme.js");
		return {
			id: ri,
			diagram: e
		};
	}, "loader")
}, ai = "wardley", oi = {
	id: ai,
	detector: /* @__PURE__ */ k((e) => /^\s*wardley-beta/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./wardleyDiagram-IVAIDKWA-DGY_aqBw.js");
		return {
			id: ai,
			diagram: e
		};
	}, "loader")
}, si = "cynefin", ci = {
	id: si,
	detector: /* @__PURE__ */ k((e) => /^\s*cynefin-beta(?:[\s:]|$)/.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./cynefinDiagram-WF24UDRP-C6L0w_gr.js");
		return {
			id: si,
			diagram: e
		};
	}, "loader")
}, li = "railroad", ui = {
	id: li,
	detector: /* @__PURE__ */ k((e) => /^\s*railroad-beta/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./railroadDiagram-Y72NRBL7-BmU1jLfD.js");
		return {
			id: li,
			diagram: e
		};
	}, "loader")
}, di = "railroadEbnf", fi = {
	id: di,
	detector: /* @__PURE__ */ k((e) => /^\s*railroad-ebnf-beta/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./ebnfDiagram-V4P3TTPT-efmJx-ik.js");
		return {
			id: di,
			diagram: e
		};
	}, "loader")
}, pi = "railroadAbnf", mi = {
	id: pi,
	detector: /* @__PURE__ */ k((e) => /^\s*railroad-abnf-beta/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./abnfDiagram-WEYOIA3C-Bwe5jFsp.js");
		return {
			id: pi,
			diagram: e
		};
	}, "loader")
}, hi = "railroadPeg", gi = {
	id: hi,
	detector: /* @__PURE__ */ k((e) => /^\s*railroad-peg-beta/i.test(e), "detector"),
	loader: /* @__PURE__ */ k(async () => {
		let { diagram: e } = await import("./pegDiagram-ECIUJNBX-Dgh2GJ0s.js");
		return {
			id: hi,
			diagram: e
		};
	}, "loader")
}, _i = !1, vi = /* @__PURE__ */ k(() => {
	_i || (_i = !0, a("error", Or, (e) => e.toLowerCase().trim() === "error"), a("---", {
		db: { clear: /* @__PURE__ */ k(() => {}, "clear") },
		styles: {},
		renderer: { draw: /* @__PURE__ */ k(() => {}, "draw") },
		parser: { parse: /* @__PURE__ */ k(() => {
			throw Error("Diagrams beginning with --- are not valid. If you were trying to use a YAML front-matter, please ensure that you've correctly opened and closed the YAML front-matter with un-indented `---` blocks");
		}, "parse") },
		init: /* @__PURE__ */ k(() => null, "init")
	}, (e) => e.toLowerCase().trimStart().startsWith("---")), o(Ar, Pr, Jr), o(Yn, Gn, Ir, _r, $n, rr, ar, sr, pr, hr, Zn, qn, Mr, tr, yr, xr, lr, Rr, Br, dr, Wr, Xr, Kr, Hr, Qr, ni, ui, fi, mi, gi, ei, oi, ci, ii));
}, "addDiagrams"), yi = /* @__PURE__ */ k(async () => {
	j.debug("Loading registered diagrams");
	let e = (await Promise.allSettled(Object.entries(y).map(async ([e, { detector: t, loader: n }]) => {
		if (n) try {
			D(e);
		} catch {
			try {
				let { diagram: e, id: r } = await n();
				a(r, e, t);
			} catch (t) {
				throw j.error(`Failed to load external diagram with key ${e}. Removing from detectors.`), delete y[e], t;
			}
		}
	}))).filter((e) => e.status === "rejected");
	if (e.length > 0) {
		j.error(`Failed to load ${e.length} external diagrams`);
		for (let t of e) j.error(t);
		throw Error(`Failed to load ${e.length} external diagrams`);
	}
}, "loadRegisteredDiagrams"), bi = "graphics-document document";
function xi(e, t) {
	e.attr("role", bi), t !== "" && e.attr("aria-roledescription", t);
}
k(xi, "setA11yDiagramInfo");
function Si(e, t, n, r) {
	if (e.insert !== void 0) {
		if (n) {
			let t = `chart-desc-${r}`;
			e.attr("aria-describedby", t), e.insert("desc", ":first-child").attr("id", t).text(n);
		}
		if (t) {
			let n = `chart-title-${r}`;
			e.attr("aria-labelledby", n), e.insert("title", ":first-child").attr("id", n).text(t);
		}
	}
}
k(Si, "addSVGa11yTitleDescription");
var Ci = class e {
	constructor(e, t, n, r, i) {
		this.type = e, this.text = t, this.db = n, this.parser = r, this.renderer = i;
	}
	static {
		k(this, "Diagram");
	}
	static async fromText(t, n = {}) {
		let r = typeof t == "string" ? {
			raw: t,
			cleaned: t
		} : t, i = O(), o = x(r.cleaned, i);
		try {
			D(o);
		} catch {
			let e = c(o);
			if (!e) throw new w(`Diagram ${o} not found.`);
			let { id: t, diagram: n } = await e();
			a(t, n);
		}
		let { db: s, parser: l, renderer: u, init: d } = D(o);
		l.parser && (l.parser.yy = s), s.clear?.(), d?.(i), n.title && s.setDiagramTitle?.(n.title);
		let f = r.cleaned;
		if (s.preserveCommentsWhenParsing && r.withComments) {
			let e = i.maxTextSize ?? h.maxTextSize;
			r.withComments.length > e ? j.warn(`Comment-preserving source exceeds maxTextSize (${r.withComments.length} > ${e}); parsing the comment-stripped text instead. Reported source positions will not account for comment lines.`) : f = r.withComments;
		}
		let p = se(f) + "\n";
		return r.frontmatterLineOffset && s.setFrontmatterLineOffset?.(r.frontmatterLineOffset), await l.parse(p), new e(o, p, s, l, u);
	}
	async render(e, t) {
		await this.renderer.draw(this.text, e, t, this);
	}
	getParser() {
		return this.parser;
	}
	getType() {
		return this.type;
	}
}, wi = [], Ti = /* @__PURE__ */ k(() => {
	wi.forEach((e) => {
		e();
	}), wi = [];
}, "attachFunctions"), Ei = /* @__PURE__ */ k((e) => e.replace(/^\s*%%(?!{)[^\n]+\n?/gm, "").trimStart(), "cleanupComments");
function Di(e) {
	let t = _(e);
	if (!t) return {
		text: e,
		metadata: {}
	};
	let n = t.indent, r = Jt(n ? t.body.split("\n").map((e) => e.startsWith(n) ? e.slice(n.length) : e).join("\n") : t.body, { schema: Gt }) ?? {};
	r = typeof r == "object" && !Array.isArray(r) ? r : {};
	let i = {};
	return r.displayMode && (i.displayMode = r.displayMode.toString()), r.title && (i.title = r.title.toString()), r.config && (i.config = r.config), {
		text: e.slice(t.length),
		metadata: i
	};
}
k(Di, "extractFrontMatter");
var Oi = /* @__PURE__ */ k((e) => e.replace(/\r\n?/g, "\n").replace(/<(\w+)([^>]*)>/g, (e, t, n) => "<" + t + n.replace(/="([^"]*)"/g, "='$1'") + ">"), "cleanupText"), ki = /* @__PURE__ */ k((e) => {
	let { text: t, metadata: n } = Di(e), { displayMode: r, title: i, config: a = {} } = n;
	return r && (a.gantt ||= {}, a.gantt.displayMode = r), {
		title: i,
		config: a,
		text: t
	};
}, "processFrontmatter"), Ai = /* @__PURE__ */ k((e) => {
	let t = B.detectInit(e) ?? {}, n = B.detectDirective(e, "wrap");
	return Array.isArray(n) ? t.wrap = n.some(({ type: e }) => e === "wrap") : n?.type === "wrap" && (t.wrap = !0), {
		text: ie(e),
		directive: t
	};
}, "processDirectives");
function ji(e) {
	let t = e, n = Oi(e), r = ki(n), i = Ai(r.text), a = ae(r.config, i.directive), o = i.text;
	return {
		code: {
			raw: t,
			cleaned: Ei(o),
			withComments: o,
			frontmatterLineOffset: n.length > r.text.length ? (n.substring(0, n.length - r.text.length).match(/\n/g) ?? []).length : 0
		},
		title: r.title,
		config: a
	};
}
k(ji, "preprocessDiagram");
function Mi(e) {
	let t = new TextEncoder().encode(e), n = Array.from(t, (e) => String.fromCodePoint(e)).join("");
	return btoa(n);
}
k(Mi, "toBase64");
var Ni = 5e4, Pi = "graph TB;a[Maximum text size in diagram exceeded];style a fill:#faa", Fi = "sandbox", Ii = "loose", Li = "http://www.w3.org/2000/svg", Ri = "http://www.w3.org/1999/xlink", zi = "http://www.w3.org/1999/xhtml", Bi = "100%", Vi = "100%", Hi = "border:0;margin:0;", Ui = "margin:0", Wi = "allow-top-navigation-by-user-activation allow-popups", Gi = "The \"iframe\" tag is not supported by your browser.", Ki = ["foreignobject"], qi = ["dominant-baseline"];
function Ji(e) {
	let n = ji(e);
	t();
	let i;
	try {
		i = x(n.code.cleaned, O());
	} catch {
		i = void 0;
	}
	return r(i), b(n.config ?? {}), n;
}
k(Ji, "processAndSetConfigs");
async function Yi(e, t) {
	vi();
	try {
		let { code: t, config: n } = Ji(e);
		return {
			diagramType: (await Ci.fromText(t)).type,
			config: n
		};
	} catch (e) {
		if (t?.suppressErrors) return !1;
		throw e;
	} finally {
		r(void 0);
	}
}
k(Yi, "parse");
var Xi = /* @__PURE__ */ k((e, t, n = []) => `.${e} ${t} ${i(`{ ${n.join(" !important; ")} !important; }`)}`, "cssImportantStyles"), Zi = /* @__PURE__ */ k((e, t = /* @__PURE__ */ new Map()) => {
	let n = new CSSStyleSheet();
	if (e.fontFamily !== void 0 && n.insertRule(`:root { --mermaid-font-family: ${e.fontFamily}}`, n.cssRules.length), e.altFontFamily !== void 0 && n.insertRule(`:root { --mermaid-alt-font-family: ${e.altFontFamily}}`, n.cssRules.length), t instanceof Map) {
		let r = E(e) ? ["> *", "span"] : [
			"rect",
			"polygon",
			"ellipse",
			"circle",
			"path"
		];
		t.forEach((e) => {
			ge(e.styles) || r.forEach((t) => {
				n.insertRule(Xi(e.id, t, e.styles), n.cssRules.length);
			}), ge(e.textStyles) || n.insertRule(Xi(e.id, "tspan", (e?.textStyles || []).map((e) => e.replace("color", "fill"))), n.cssRules.length);
		});
	}
	let r = "";
	if (e.themeCSS !== void 0) {
		if (typeof n.replaceSync == "function") {
			let t = new CSSStyleSheet();
			t.replaceSync(e.themeCSS), r = v(t) + "\n";
		} else r += `${e.themeCSS}
`;
	}
	return r + v(n);
}, "createCssStyles"), Qi = /* @__PURE__ */ k((e, t) => Vn(In(`${e}{${t}}`), Un([/* @__PURE__ */ k(function(t, n, r, i) {
	if (t.type === "rule" && Array.isArray(t.props)) {
		if (t.parent && t.parent.type === "@keyframes") return;
		t.props = t.props.map((n) => n === e && Array.isArray(t.children) && t.children.every((e) => e.type === "decl" && (/* @__PURE__ */ new Set([
			"font-family",
			"font-size",
			"fill"
		])).has(e.props)) ? n : !n.startsWith(`${e} `) && !n.startsWith(`${e}>`) || n.startsWith(`${e} ||`) ? `${e} ${n}` : n);
	} else t.type.startsWith("@") && ([
		"@media",
		"@supports",
		"@layer",
		"@scope",
		"@container",
		"@starting-style",
		"@keyframes"
	].includes(t.type) || (j.warn(`Removing unsupported at-rule ${t.type} from CSS`), t.type = nn));
}, "addNamespace"), Hn])), "compileCSS"), $i = /* @__PURE__ */ k((e, t, n, r) => {
	let i = Zi(e, n);
	return Qi(r, f(t, i, {
		...e.themeVariables,
		theme: e.theme,
		look: e.look
	}, r));
}, "createUserStyles"), ea = /* @__PURE__ */ k((e = "", t, n) => {
	let r = e;
	return !n && !t && (r = r.replace(/marker-end="url\([\d+./:=?A-Za-z-]*?#/g, "marker-end=\"url(#")), r = te(r), r = r.replace(/<br>/g, "<br/>"), r;
}, "cleanUpSvgCode"), ta = /* @__PURE__ */ k((e = "", t) => `<iframe style="width:${Bi};height:${t?.viewBox?.baseVal?.height ? t.viewBox.baseVal.height + "px" : Vi};${Hi}" src="data:text/html;charset=UTF-8;base64,${Mi(`<body style="${Ui}">${e}</body>`)}" sandbox="${Wi}">
  ${Gi}
</iframe>`, "putIntoIFrame"), na = /* @__PURE__ */ k((e, t, n, r, i) => {
	let a = e.append("div");
	a.attr("id", n), r && a.attr("style", r);
	let o = a.append("svg").attr("id", t).attr("width", "100%").attr("xmlns", Li);
	return i && o.attr("xmlns:xlink", i), o.append("g"), e;
}, "appendDivSvgG");
function ra(e, t) {
	return e.append("iframe").attr("id", t).attr("style", "width: 100%; height: 100%;").attr("sandbox", "");
}
k(ra, "sandboxedIframe");
var ia = /* @__PURE__ */ k((e, t, n, r) => {
	e.getElementById(t)?.remove(), e.getElementById(n)?.remove(), e.getElementById(r)?.remove();
}, "removeExistingElements"), aa = /* @__PURE__ */ k(async function(e, t, n) {
	vi();
	let r = Ji(t), i = r.code;
	t = i.cleaned;
	let a = O();
	j.debug(a), t.length > (a?.maxTextSize ?? Ni) && (t = Pi, i = {
		raw: t,
		cleaned: t
	});
	let o = `#${e}`, s = "i" + e, c = "#" + s, l = "d" + e, u = "#" + l, d = /* @__PURE__ */ k(() => {
		let e = M(p ? c : u).node();
		e && "remove" in e && e.remove();
	}, "removeTempElements"), f = M(document.body), p = a.securityLevel === Fi, m = a.securityLevel === Ii, h = a.fontFamily;
	if (n !== void 0) {
		if (n && (n.innerHTML = ""), p) {
			let e = ra(M(n), s);
			f = M(e.nodes()[0].contentDocument.body), f.node().style.margin = "0";
		} else f = M(n);
		na(f, e, l, `font-family: ${h}`, Ri);
	} else {
		if (ia(document, e, l, s), p) {
			let e = ra(M(document.body), s);
			f = M(e.nodes()[0].contentDocument.body), f.node().style.margin = "0";
		} else f = M("body");
		na(f, e, l);
	}
	let _, v;
	try {
		_ = await Ci.fromText(i, { title: r.title });
	} catch (e) {
		if (a.suppressErrorRendering) throw d(), e;
		_ = await Ci.fromText("error"), v = e, _.db.setErrorMessage?.(oe(e));
	}
	let y = f.select(u).node(), b = _.type, x = y.firstChild, S = x.firstChild, C = _.renderer.getClasses?.(t, _), w = $i(a, b, C, o), T = document.createElement("style");
	T.innerHTML = w, x.insertBefore(T, S);
	try {
		await _.renderer.draw(t, e, "12.2.0", _);
	} catch (n) {
		throw a.suppressErrorRendering ? d() : Er.draw(t, e, "12.2.0"), n;
	}
	let E = f.select(`${u} svg`), D = _.db.getAccTitle?.(), A = _.db.getAccDescription?.();
	la(b, E, D, A);
	let N = (/* @__PURE__ */ k(() => {
		f.select(`[id="${e}"]`).selectAll("foreignobject > *").attr("xmlns", zi);
		let t = f.select(u).node().innerHTML;
		if (j.debug("config.arrowMarkerAbsolute", a.arrowMarkerAbsolute), t = ea(t, p, g(a.arrowMarkerAbsolute)), p) {
			let e = f.select(u + " svg").node();
			t = ta(t, e);
		} else m || (t = pe.sanitize(t, {
			ADD_TAGS: Ki,
			ADD_ATTR: qi,
			HTML_INTEGRATION_POINTS: { foreignobject: !0 }
		}));
		return Ti(), t;
	}, "serializeSvg"))();
	if (v) throw v;
	return d(), {
		diagramType: b,
		svg: N,
		bindFunctions: _.db.bindFunctions
	};
}, "renderDiagram"), oa = /* @__PURE__ */ k(async function(e, t, n) {
	try {
		return await aa(e, t, n);
	} finally {
		r(void 0);
	}
}, "render");
function sa(e = {}) {
	let t = S({}, e);
	t?.fontFamily && !t.themeVariables?.fontFamily && (t.themeVariables ||= {}, t.themeVariables.fontFamily = t.fontFamily), u(t);
	let r = T.theme;
	t?.theme && Object.hasOwn(s, t.theme) ? t.themeVariables = s[t.theme].getThemeVariables(t.themeVariables) : t && (t.theme != null && t.theme !== "null" && (t.theme = r), t.themeVariables = s[r].getThemeVariables(t.themeVariables));
	let i = typeof t == "object" ? n(t) : l();
	A(i.logLevel), vi();
}
k(sa, "initialize");
var ca = /* @__PURE__ */ k((e, t = {}) => {
	let { code: n } = ji(e);
	return Ci.fromText(n, t);
}, "getDiagramFromText");
function la(e, t, n, r) {
	xi(t, e), Si(t, n, r, t.attr("id"));
}
k(la, "addA11yInfo");
var Q = Object.freeze({
	render: oa,
	parse: Yi,
	getDiagramFromText: ca,
	initialize: sa,
	getConfig: O,
	setConfig: d,
	getSiteConfig: l,
	updateSiteConfig: e,
	reset: /* @__PURE__ */ k(() => {
		t();
	}, "reset"),
	globalReset: /* @__PURE__ */ k(() => {
		t(T);
	}, "globalReset"),
	defaultConfig: T
});
A(O().logLevel), t(O());
var ua = /* @__PURE__ */ k((e, t, n) => {
	j.warn(e), ce(e) ? (n && n(e.str, e.hash), t.push({
		...e,
		message: e.str,
		error: e
	})) : (n && n(e), e instanceof Error && t.push({
		str: e.message,
		message: e.message,
		hash: e.name,
		error: e
	}));
}, "handleError"), da = /* @__PURE__ */ k(async function(e = { querySelector: ".mermaid" }) {
	try {
		await fa(e);
	} catch (t) {
		if (ce(t) && j.error(t.str), $.parseError && $.parseError(t), !e.suppressErrors) throw j.error("Use the suppressErrors option to suppress these errors"), t;
	}
}, "run"), fa = /* @__PURE__ */ k(async function({ postRenderCallback: e, querySelector: t, nodes: n } = { querySelector: ".mermaid" }) {
	let r = Q.getConfig();
	j.debug(`${e ? "" : "No "}Callback function found`);
	let i;
	if (n) i = n;
	else if (t) i = document.querySelectorAll(t);
	else throw Error("Nodes and querySelector are both undefined");
	j.debug(`Found ${i.length} diagrams`), r?.startOnLoad !== void 0 && (j.debug("Start On Load: " + r?.startOnLoad), Q.updateSiteConfig({ startOnLoad: r?.startOnLoad }));
	let a = new B.InitIDGenerator(r.deterministicIds, r.deterministicIDSeed), o, s = [];
	for (let t of Array.from(i)) {
		if (j.info("Rendering diagram: " + t.id), t.getAttribute("data-processed")) continue;
		t.setAttribute("data-processed", "true");
		let n = `mermaid-${a.next()}`;
		o = t.innerHTML, o = V(B.entityDecode(o)).trim().replace(/<br\s*\/?>/gi, "<br/>");
		let r = B.detectInit(o);
		r && j.debug("Detected early reinit: ", r);
		try {
			let { svg: r, bindFunctions: i } = await Sa(n, o, t);
			t.innerHTML = r, e && await e(n), i && i(t);
		} catch (e) {
			ua(e, s, $.parseError);
		}
	}
	if (s.length > 0) throw s[0];
}, "runThrowsErrors"), pa = /* @__PURE__ */ k(function(e) {
	Q.initialize(e);
}, "initialize"), ma = /* @__PURE__ */ k(async function(e, t, n) {
	j.warn("mermaid.init is deprecated. Please use run instead."), e && pa(e);
	let r = {
		postRenderCallback: n,
		querySelector: ".mermaid"
	};
	typeof t == "string" ? r.querySelector = t : t && (r.nodes = t instanceof HTMLElement ? [t] : t), await da(r);
}, "init"), ha = /* @__PURE__ */ k(async (e, { lazyLoad: t = !0 } = {}) => {
	vi(), o(...e), t === !1 && await yi();
}, "registerExternalDiagrams"), ga = /* @__PURE__ */ k(function() {
	if ($.startOnLoad) {
		let { startOnLoad: e } = Q.getConfig();
		e && $.run().catch((e) => j.error("Mermaid failed to initialize", e));
	}
}, "contentLoaded");
typeof document < "u" && window.addEventListener("load", ga, !1);
var _a = /* @__PURE__ */ k(function(e) {
	$.parseError = e;
}, "setParseErrorHandler"), va = [], ya = !1, ba = /* @__PURE__ */ k(async () => {
	if (!ya) {
		for (ya = !0; va.length > 0;) {
			let e = va.shift();
			if (e) try {
				await e();
			} catch (e) {
				j.error("Error executing queue", e);
			}
		}
		ya = !1;
	}
}, "executeQueue"), xa = /* @__PURE__ */ k(async (e, t) => new Promise((n, r) => {
	let i = /* @__PURE__ */ k(() => new Promise((i, a) => {
		Q.parse(e, t).then((e) => {
			i(e), n(e);
		}, (e) => {
			j.error("Error parsing", e), $.parseError?.(e), a(e), r(e);
		});
	}), "performCall");
	va.push(i), ba().catch(r);
}), "parse"), Sa = /* @__PURE__ */ k((e, t, n) => new Promise((r, i) => {
	let a = /* @__PURE__ */ k(() => new Promise((a, o) => {
		Q.render(e, t, n).then((e) => {
			a(e), r(e);
		}, (e) => {
			j.error("Error parsing", e), $.parseError?.(e), o(e), i(e);
		});
	}), "performCall");
	va.push(a), ba().catch(i);
}), "render"), $ = {
	startOnLoad: !0,
	mermaidAPI: Q,
	parse: xa,
	render: Sa,
	init: ma,
	run: da,
	registerExternalDiagrams: ha,
	registerLayoutLoaders: be,
	initialize: pa,
	parseError: void 0,
	contentLoaded: ga,
	setParseErrorHandler: _a,
	detectType: x,
	registerIconPacks: fe,
	getRegisteredDiagramsMetadata: /* @__PURE__ */ k(() => Object.keys(y).map((e) => ({ id: e })), "getRegisteredDiagramsMetadata")
}, Ca = $;
export { me as a, Ca as default, Se as i, Jt as n, we as r, Gt as t };
