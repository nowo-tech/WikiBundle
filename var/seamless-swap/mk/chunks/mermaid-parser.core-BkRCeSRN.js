import { C as e, S as t, _ as n, a as r, b as i, c as a, d as o, f as ee, g as te, h as ne, i as s, l as re, m as ie, n as c, o as l, p as ae, r as oe, s as se, t as u, u as d, v as ce, w as f, x as p, y as le } from "./chunk-NGNAAXSQ-DA7bETKk.js";
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-24IY7LWP.mjs
var ue = class extends u {
	static {
		p(this, "ArchitectureTokenBuilder");
	}
	constructor() {
		super(["architecture"]);
	}
}, de = class extends c {
	static {
		p(this, "ArchitectureValueConverter");
	}
	runCustomConverter(e, t, n) {
		if (e.name === "ARCH_ICON") return t.replace(/[()]/g, "").trim();
		if (e.name === "ARCH_TEXT_ICON") return t.replace(/["()]/g, "");
		if (e.name === "ARCH_TITLE") {
			let e = t.replace(/^\[|]$/g, "").trim();
			return (e.startsWith("\"") && e.endsWith("\"") || e.startsWith("'") && e.endsWith("'")) && (e = e.slice(1, -1), e = e.replace(/\\"/g, "\"").replace(/\\'/g, "'")), e.trim();
		}
	}
}, m = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new ue(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new de(), "ValueConverter")
} };
function h(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), oe, m);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Architecture: i
	};
}
p(h, "createArchitectureServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-VPELOWWC.mjs
var fe = class extends u {
	static {
		p(this, "CynefinTokenBuilder");
	}
	constructor() {
		super(["cynefin-beta"]);
	}
}, g = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new fe(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
} };
function _(n = l) {
	let i = f(e(n), d), a = f(t({ shared: i }), r, g);
	return i.ServiceRegistry.register(a), {
		shared: i,
		Cynefin: a
	};
}
p(_, "createCynefinServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-TPMEZKFX.mjs
var pe = class extends u {
	static {
		p(this, "EventModelingTokenBuilder");
	}
	constructor() {
		super(["eventmodeling"]);
	}
}, me = /* @__PURE__ */ new Set(["cmd", "command"]), v = /* @__PURE__ */ new Set(["evt", "event"]), y = /* @__PURE__ */ new Set(["rmo", "readmodel"]), b = /* @__PURE__ */ new Set(["pcr", "processor"]), x = /* @__PURE__ */ new Set(["ui"]);
function S(e) {
	let t = e.validation.EventModelingValidator, n = e.validation.ValidationRegistry;
	if (n) {
		let e = {
			EmTimeFrame: t.checkSourceFrameTypes.bind(t),
			EmResetFrame: t.checkSourceFrameTypes.bind(t)
		};
		n.register(e, t);
	}
}
p(S, "registerValidationChecks");
var he = class {
	static {
		p(this, "EventModelingValidator");
	}
	checkSourceFrameTypes(e, t) {
		e.sourceFrames.length !== 0 && (me.has(e.modelEntityType) ? this.validateSources(e, /* @__PURE__ */ new Set([...x, ...b]), "command", "ui or processor", t) : v.has(e.modelEntityType) ? this.validateSources(e, me, "event", "command", t) : y.has(e.modelEntityType) ? this.validateSources(e, v, "read model", "event", t) : b.has(e.modelEntityType) ? this.validateSources(e, y, "processor", "read model", t) : x.has(e.modelEntityType) && this.validateSources(e, y, "ui", "read model", t));
	}
	validateSources(e, t, n, r, i) {
		for (let a of e.sourceFrames) {
			let o = a.ref;
			o !== void 0 && !t.has(o.modelEntityType) && i("error", `A ${n} can only receive input from a ${r}, not from '${o.modelEntityType}'.`, {
				node: e,
				property: "sourceFrames"
			});
		}
	}
}, C = {
	parser: {
		TokenBuilder: /* @__PURE__ */ p(() => new pe(), "TokenBuilder"),
		ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
	},
	validation: { EventModelingValidator: /* @__PURE__ */ p(() => new he(), "EventModelingValidator") }
};
function w(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), se, C);
	return r.ServiceRegistry.register(i), S(i), {
		shared: r,
		EventModel: i
	};
}
p(w, "createEventModelingServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-VZTHESGH.mjs
var ge = class extends u {
	static {
		p(this, "GitGraphTokenBuilder");
	}
	constructor() {
		super(["gitGraph"]);
	}
}, T = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new ge(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
} };
function E(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), a, T);
	return r.ServiceRegistry.register(i), {
		shared: r,
		GitGraph: i
	};
}
p(E, "createGitGraphServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-3M4EKCLJ.mjs
var _e = class extends u {
	static {
		p(this, "InfoTokenBuilder");
	}
	constructor() {
		super(["info", "showInfo"]);
	}
}, D = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new _e(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
} };
function O(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), re, D);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Info: i
	};
}
p(O, "createInfoServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-OD3NTWWA.mjs
var ve = class extends u {
	static {
		p(this, "PacketTokenBuilder");
	}
	constructor() {
		super(["packet"]);
	}
}, k = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new ve(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
} };
function A(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), o, k);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Packet: i
	};
}
p(A, "createPacketServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-QNH66VMT.mjs
var ye = class extends u {
	static {
		p(this, "PieTokenBuilder");
	}
	constructor() {
		super(["pie", "showData"]);
	}
}, be = class extends c {
	static {
		p(this, "PieValueConverter");
	}
	runCustomConverter(e, t, n) {
		if (e.name === "PIE_SECTION_LABEL") return t.replace(/"/g, "").trim();
	}
}, j = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new ye(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new be(), "ValueConverter")
} };
function M(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), ee, j);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Pie: i
	};
}
p(M, "createPieServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-VTWWFHGB.mjs
var xe = class extends u {
	static {
		p(this, "RadarTokenBuilder");
	}
	constructor() {
		super(["radar-beta"]);
	}
}, N = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new xe(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new s(), "ValueConverter")
} };
function P(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), ae, N);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Radar: i
	};
}
p(P, "createRadarServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-ZO67DCNQ.mjs
var Se = class extends u {
	static {
		p(this, "RailroadTokenBuilder");
	}
	constructor() {
		super(["railroad-beta"]);
	}
}, F = /* @__PURE__ */ p((e) => {
	let t = e.slice(1, -1), n = "";
	for (let e = 0; e < t.length; e++) {
		let r = t[e];
		if (r === "\\" && e + 1 < t.length) {
			e++;
			let r = t[e];
			switch (r) {
				case "n":
					n += "\n";
					break;
				case "r":
					n += "\r";
					break;
				case "t":
					n += "	";
					break;
				default: n += r;
			}
		} else n += r;
	}
	return n;
}, "decodeEscapedString"), Ce = class extends c {
	static {
		p(this, "RailroadValueConverter");
	}
	runConverter(e, t, n) {
		let r = super.runConverter(e, t, n);
		if (e.name === "TITLE" && typeof r == "string") {
			let e = r.trim();
			if (e.startsWith("\"") && e.endsWith("\"") || e.startsWith("'") && e.endsWith("'")) return F(e);
		}
		return r;
	}
	runCustomConverter(e, t, n) {
		if (e.name === "RR_STRING") return F(t);
	}
}, I = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new Se(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new Ce(), "ValueConverter")
} };
function L(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), te, I);
	return r.ServiceRegistry.register(i), {
		shared: r,
		Railroad: i
	};
}
p(L, "createRailroadServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-YK26KJH5.mjs
var we = class extends u {
	static {
		p(this, "RailroadAbnfTokenBuilder");
	}
	constructor() {
		super(["railroad-abnf-beta"]);
	}
}, Te = class extends c {
	static {
		p(this, "RailroadAbnfValueConverter");
	}
	runConverter(e, t, n) {
		let r = super.runConverter(e, t, n);
		if (e.name === "TITLE" && typeof r == "string") {
			let e = r.trim();
			if (e.startsWith("\"") && e.endsWith("\"") || e.startsWith("'") && e.endsWith("'")) return e.slice(1, -1);
		}
		return r;
	}
	runCustomConverter(e, t, n) {
		if (e.name === "ABNF_STRING") return t.slice(1, -1);
	}
}, Ee = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new we(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new Te(), "ValueConverter")
} };
function R(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), ie, Ee);
	return r.ServiceRegistry.register(i), {
		shared: r,
		RailroadAbnf: i
	};
}
p(R, "createRailroadAbnfServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-F6S3BTY2.mjs
var De = class extends u {
	static {
		p(this, "RailroadEbnfTokenBuilder");
	}
	constructor() {
		super(["railroad-ebnf-beta"]);
	}
}, z = /* @__PURE__ */ p((e) => {
	let t = e.slice(1, -1), n = "";
	for (let e = 0; e < t.length; e++) {
		let r = t[e];
		if (r === "\\" && e + 1 < t.length) {
			e++;
			let r = t[e];
			switch (r) {
				case "n":
					n += "\n";
					break;
				case "r":
					n += "\r";
					break;
				case "t":
					n += "	";
					break;
				default: n += r;
			}
		} else n += r;
	}
	return n;
}, "decodeEscapedString"), Oe = class extends c {
	static {
		p(this, "RailroadEbnfValueConverter");
	}
	runConverter(e, t, n) {
		let r = super.runConverter(e, t, n);
		if (e.name === "TITLE" && typeof r == "string") {
			let e = r.trim();
			if (e.startsWith("\"") && e.endsWith("\"") || e.startsWith("'") && e.endsWith("'")) return z(e);
		}
		return r;
	}
	runCustomConverter(e, t, n) {
		if (e.name === "EBNF_STRING") return z(t);
		if (e.name === "EBNF_SPECIAL_SEQUENCE") return t.slice(1, -1).trim();
	}
}, B = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new De(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new Oe(), "ValueConverter")
} };
function V(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), ne, B);
	return r.ServiceRegistry.register(i), {
		shared: r,
		RailroadEbnf: i
	};
}
p(V, "createRailroadEbnfServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-BP2KR52E.mjs
var ke = class extends u {
	static {
		p(this, "RailroadPegTokenBuilder");
	}
	constructor() {
		super(["railroad-peg-beta"]);
	}
}, H = /* @__PURE__ */ p((e) => {
	let t = e.slice(1, -1), n = "";
	for (let e = 0; e < t.length; e++) {
		let r = t[e];
		if (r === "\\" && e + 1 < t.length) {
			e++;
			let r = t[e];
			switch (r) {
				case "n":
					n += "\n";
					break;
				case "r":
					n += "\r";
					break;
				case "t":
					n += "	";
					break;
				default: n += r;
			}
		} else n += r;
	}
	return n;
}, "decodeEscapedString"), Ae = class extends c {
	static {
		p(this, "RailroadPegValueConverter");
	}
	runConverter(e, t, n) {
		let r = super.runConverter(e, t, n);
		if (e.name === "TITLE" && typeof r == "string") {
			let e = r.trim();
			if (e.startsWith("\"") && e.endsWith("\"") || e.startsWith("'") && e.endsWith("'")) return H(e);
		}
		return r;
	}
	runCustomConverter(e, t, n) {
		if (e.name === "PEG_STRING") return H(t);
	}
}, U = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new ke(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new Ae(), "ValueConverter")
} };
function W(r = l) {
	let i = f(e(r), d), a = f(t({ shared: i }), n, U);
	return i.ServiceRegistry.register(a), {
		shared: i,
		RailroadPeg: a
	};
}
p(W, "createRailroadPegServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-KPI5JJXK.mjs
var je = class extends c {
	static {
		p(this, "TreeViewValueConverter");
	}
	runCustomConverter(e, t, n) {
		if (e.name === "INDENTATION") return t?.length || 0;
		if (e.name === "QUOTED_NAME") return t.substring(1, t.length - 1);
		if (e.name === "BARE_NAME") return t.replace(/[\t ]+$/, "");
		if (e.name === "CLASS_ANNOTATION") return t.trim().substring(3).trim();
		if (e.name === "ICON_ANNOTATION") {
			let e = t.trim();
			return e.substring(5, e.length - 1);
		}
		if (e.name === "DESC_ANNOTATION") return t.trim().substring(2).trim();
	}
}, Me = class extends u {
	static {
		p(this, "TreeViewTokenBuilder");
	}
	constructor() {
		super(["treeView-beta"]);
	}
}, G = { parser: {
	TokenBuilder: /* @__PURE__ */ p(() => new Me(), "TokenBuilder"),
	ValueConverter: /* @__PURE__ */ p(() => new je(), "ValueConverter")
} };
function K(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), ce, G);
	return r.ServiceRegistry.register(i), {
		shared: r,
		TreeView: i
	};
}
p(K, "createTreeViewServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-4S7OKTRW.mjs
var Ne = class extends u {
	static {
		p(this, "TreemapTokenBuilder");
	}
	constructor() {
		super(["treemap"]);
	}
}, Pe = /classDef\s+([A-Z_a-z]\w+)(?:\s+([^\n\r;]*))?;?/, Fe = class extends c {
	static {
		p(this, "TreemapValueConverter");
	}
	runCustomConverter(e, t, n) {
		if (e.name === "NUMBER2") return parseFloat(t.replace(/,/g, ""));
		if (e.name === "SEPARATOR" || e.name === "STRING2") return t.substring(1, t.length - 1);
		if (e.name === "INDENTATION") return t.length;
		if (e.name === "ClassDef") {
			if (typeof t != "string") return t;
			let e = Pe.exec(t);
			if (e) return {
				$type: "ClassDefStatement",
				className: e[1],
				styleText: e[2] || void 0
			};
		}
	}
};
function q(e) {
	let t = e.validation.TreemapValidator, n = e.validation.ValidationRegistry;
	if (n) {
		let e = { Treemap: t.checkSingleRoot.bind(t) };
		n.register(e, t);
	}
}
p(q, "registerValidationChecks");
var Ie = class {
	static {
		p(this, "TreemapValidator");
	}
	checkSingleRoot(e, t) {
		let n;
		for (let r of e.TreemapRows) r.item && (n === void 0 && r.indent === void 0 ? n = 0 : (r.indent === void 0 || n !== void 0 && n >= parseInt(r.indent, 10)) && t("error", "Multiple root nodes are not allowed in a treemap.", {
			node: r,
			property: "item"
		}));
	}
}, J = {
	parser: {
		TokenBuilder: /* @__PURE__ */ p(() => new Ne(), "TokenBuilder"),
		ValueConverter: /* @__PURE__ */ p(() => new Fe(), "ValueConverter")
	},
	validation: { TreemapValidator: /* @__PURE__ */ p(() => new Ie(), "TreemapValidator") }
};
function Y(n = l) {
	let r = f(e(n), d), i = f(t({ shared: r }), le, J);
	return r.ServiceRegistry.register(i), q(i), {
		shared: r,
		Treemap: i
	};
}
p(Y, "createTreemapServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/chunks/mermaid-parser.core/chunk-53FOQ5SW.mjs
var Le = class extends c {
	static {
		p(this, "WardleyValueConverter");
	}
	runCustomConverter(e, t, n) {
		switch (e.name.toUpperCase()) {
			case "LINK_LABEL": return t.substring(1).trim();
			default: return;
		}
	}
}, X = { parser: { ValueConverter: /* @__PURE__ */ p(() => new Le(), "ValueConverter") } };
function Z(n = l) {
	let r = f(e(n), d), a = f(t({ shared: r }), i, X);
	return r.ServiceRegistry.register(a), {
		shared: r,
		Wardley: a
	};
}
p(Z, "createWardleyServices");
// node_modules/.pnpm/@mermaid-js+parser@2.0.1/node_modules/@mermaid-js/parser/dist/mermaid-parser.core.mjs
var Q = {}, Re = {
	info: /* @__PURE__ */ p(async () => {
		let { createInfoServices: e } = await import("./info-OHQRW6UA-DK41A4_m.js");
		Q.info = e().Info.parser.LangiumParser;
	}, "info"),
	packet: /* @__PURE__ */ p(async () => {
		let { createPacketServices: e } = await import("./packet-JDAUHWVQ-C-kogiee.js");
		Q.packet = e().Packet.parser.LangiumParser;
	}, "packet"),
	pie: /* @__PURE__ */ p(async () => {
		let { createPieServices: e } = await import("./pie-XZMESJXO-DG1SbBSV.js");
		Q.pie = e().Pie.parser.LangiumParser;
	}, "pie"),
	treeView: /* @__PURE__ */ p(async () => {
		let { createTreeViewServices: e } = await import("./treeView-D4JQ5SDB-B8-LUEvj.js");
		Q.treeView = e().TreeView.parser.LangiumParser;
	}, "treeView"),
	architecture: /* @__PURE__ */ p(async () => {
		let { createArchitectureServices: e } = await import("./architecture-WOLXFQ4H-DzMvaUOP.js");
		Q.architecture = e().Architecture.parser.LangiumParser;
	}, "architecture"),
	gitGraph: /* @__PURE__ */ p(async () => {
		let { createGitGraphServices: e } = await import("./gitGraph-VSP46ZUC-K6A9ia21.js");
		Q.gitGraph = e().GitGraph.parser.LangiumParser;
	}, "gitGraph"),
	eventmodeling: /* @__PURE__ */ p(async () => {
		let { createEventModelingServices: e } = await import("./eventmodeling-K75KTNOO-BHcNC0-d.js");
		Q.eventmodeling = e().EventModel.parser.LangiumParser;
	}, "eventmodeling"),
	radar: /* @__PURE__ */ p(async () => {
		let { createRadarServices: e } = await import("./radar-ABXABTNO-1RWd_MKQ.js");
		Q.radar = e().Radar.parser.LangiumParser;
	}, "radar"),
	railroad: /* @__PURE__ */ p(async () => {
		let { createRailroadServices: e } = await import("./railroad-I3PHUGI6-zqqgeHJy.js");
		Q.railroad = e().Railroad.parser.LangiumParser;
	}, "railroad"),
	railroadEbnf: /* @__PURE__ */ p(async () => {
		let { createRailroadEbnfServices: e } = await import("./railroad-ebnf-DWYD2UWJ-DrCC1hcP.js");
		Q.railroadEbnf = e().RailroadEbnf.parser.LangiumParser;
	}, "railroadEbnf"),
	railroadAbnf: /* @__PURE__ */ p(async () => {
		let { createRailroadAbnfServices: e } = await import("./railroad-abnf-LNEFI6M7-C5so-v9H.js");
		Q.railroadAbnf = e().RailroadAbnf.parser.LangiumParser;
	}, "railroadAbnf"),
	railroadPeg: /* @__PURE__ */ p(async () => {
		let { createRailroadPegServices: e } = await import("./railroad-peg-JR7BVNR7-DkINPvVE.js");
		Q.railroadPeg = e().RailroadPeg.parser.LangiumParser;
	}, "railroadPeg"),
	treemap: /* @__PURE__ */ p(async () => {
		let { createTreemapServices: e } = await import("./treemap-SAJKECNS-DlkfVNQA.js");
		Q.treemap = e().Treemap.parser.LangiumParser;
	}, "treemap"),
	wardley: /* @__PURE__ */ p(async () => {
		let { createWardleyServices: e } = await import("./wardley-7MLQ67FV-E2Mo2wQ6.js");
		Q.wardley = e().Wardley.parser.LangiumParser;
	}, "wardley"),
	cynefin: /* @__PURE__ */ p(async () => {
		let { createCynefinServices: e } = await import("./cynefin-EF2NZ3EQ-iFtEZoJR.js");
		Q.cynefin = e().Cynefin.parser.LangiumParser;
	}, "cynefin")
};
async function ze(e, t) {
	let n = Re[e];
	if (!n) throw Error(`Unknown diagram type: ${e}`);
	Q[e] || await n();
	let r = Q[e].parse(t);
	if (r.lexerErrors.length > 0 || r.parserErrors.length > 0) throw new Be(r);
	return r.value;
}
p(ze, "parse");
var $ = /* @__PURE__ */ p((e) => e !== void 0 && e >= 0 ? e : "?", "formatLocation"), Be = class extends Error {
	constructor(e) {
		let t = e.lexerErrors.map((e) => `Lexer error on line ${$(e.line)}, column ${$(e.column)}: ${e.message}`).join("\n"), n = e.parserErrors.map((e) => `Parse error on line ${$(e.token.startLine)}, column ${$(e.token.startColumn)}: ${e.message}`).join("\n");
		super(`Parsing failed: ${t} ${n}`), this.result = e;
	}
	static {
		p(this, "MermaidParseError");
	}
};
export { _ as A, D as C, C as D, E, h as M, w as O, A as S, T, N as _, J as a, M as b, K as c, B as d, V as f, L as g, I as h, Z as i, m as j, g as k, U as l, R as m, ze as n, Y as o, Ee as p, X as r, G as s, Be as t, W as u, P as v, O as w, k as x, j as y };
