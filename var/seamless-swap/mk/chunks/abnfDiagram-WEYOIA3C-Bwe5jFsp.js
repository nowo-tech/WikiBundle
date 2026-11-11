import { n as e, r as t, t as n } from "./chunk-2E7WHVMD-Cw81DrOk.js";
import { t as r } from "./chunk-JWPE2WC7-DeVQRhNz.js";
import "./mermaid.core-BH4raDJh.js";
import "./chunk-DSNLSHJZ-DRpVu4wY.js";
import { m as i } from "./src-ckL58JCF.js";
import { n as a } from "./chunk-Y2CYZVJY-fpkXlLv-.js";
import { m as o, t as s } from "./mermaid-parser.core-BkRCeSRN.js";
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/abnfDiagram-WEYOIA3C.mjs
var c = o().RailroadAbnf.parser.LangiumParser, l = /* @__PURE__ */ a((e) => {
	let t = e.alternatives.map(u);
	return t.length === 1 ? t[0] : {
		type: "choice",
		alternatives: t
	};
}, "transformAlternation"), u = /* @__PURE__ */ a((e) => {
	let t = e.elements.map(f);
	return t.length === 1 ? t[0] : {
		type: "sequence",
		elements: t
	};
}, "transformConcatenation"), d = /* @__PURE__ */ a((e) => {
	if (e.includes("*")) {
		let [t, n] = e.split("*");
		return {
			min: t ? parseInt(t, 10) : 0,
			max: n ? parseInt(n, 10) : Infinity
		};
	}
	let t = parseInt(e, 10);
	return {
		min: t,
		max: t
	};
}, "parseRepeat"), f = /* @__PURE__ */ a((e) => {
	let t = p(e.primary);
	if (!e.repeat) return t;
	let { min: n, max: r } = d(e.repeat);
	return n === 0 && r === 1 ? {
		type: "optional",
		element: t
	} : {
		type: "repetition",
		element: t,
		min: n,
		max: r
	};
}, "transformElement"), p = /* @__PURE__ */ a((e) => {
	switch (e.$type) {
		case "AbnfStringLiteral": return {
			type: "terminal",
			value: e.value
		};
		case "AbnfNumVal": return {
			type: "terminal",
			value: e.value
		};
		case "AbnfRuleName": return {
			type: "nonterminal",
			name: e.name
		};
		case "AbnfGroup": return l(e.element);
		case "AbnfOptionalGroup": return {
			type: "optional",
			element: l(e.element)
		};
		default: throw Error(`Unsupported ABNF primary node: ${e.$type}`);
	}
}, "transformPrimary"), m = /* @__PURE__ */ a((e) => ({
	name: e.name,
	definition: l(e.definition)
}), "transformRule"), h = /* @__PURE__ */ a((e) => {
	r(e, n), e.title && n.setTitle(e.title), e.rules.map((e) => n.addRule(m(e)));
}, "populateDb"), g = {
	parser: {
		parse: /* @__PURE__ */ a((e) => {
			n.clear(), i.debug("[ABNF Parser] Starting Langium parse");
			let t = c.parse(e);
			if (t.lexerErrors.length > 0 || t.parserErrors.length > 0) throw new s(t);
			let r = t.value;
			i.debug("[ABNF Parser] Parsed rules:", r.rules.length), h(r), i.debug("[ABNF Parser] Parse complete");
		}, "parse"),
		parser: { yy: n }
	},
	db: n,
	renderer: t,
	styles: e
};
export { g as diagram };
