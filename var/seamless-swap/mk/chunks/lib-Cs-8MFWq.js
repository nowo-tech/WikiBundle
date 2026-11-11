import { D as e, G as t, H as n, J as r, M as i, Mt as a, Nt as o, Q as s, R as c, St as l, X as u, Y as d, ct as f, dt as ee, et as p, in as m, j as te, jt as h, l as g, m as ne, mt as re, nt as ie, ot as _, st as v, tt as ae, ut as y, vt as oe, wt as se } from "./lib-9QpRoHAM.js";
import { a as b, c as x, d as S, i as C, n as ce, o as le, p as w, r as T, s as E, t as D, u as O } from "./lib-DaU_YkmU.js";
import { n as ue, r as de, t as fe } from "./dist-BmAqUYId.js";
// node_modules/.pnpm/mdast-util-definitions@6.0.0/node_modules/mdast-util-definitions/lib/index.js
function pe(e) {
	let t = /* @__PURE__ */ new Map();
	if (!e || !e.type) throw Error("mdast-util-definitions expected node");
	return h(e, "definition", function(e) {
		let n = me(e.identifier);
		n && !t.get(n) && t.set(n, e);
	}), n;
	function n(e) {
		let n = me(e);
		return t.get(n);
	}
}
function me(e) {
	return String(e || "").toUpperCase();
}
// node_modules/.pnpm/remark-inline-links@7.0.0/node_modules/remark-inline-links/lib/index.js
function he() {
	return function(e) {
		let t = pe(e);
		h(e, function(e, n, r) {
			if (e.type === "definition" && r !== void 0 && typeof n == "number") return r.children.splice(n, 1), [a, n];
			if (e.type === "imageReference" || e.type === "linkReference") {
				let i = t(e.identifier);
				if (i && r && typeof n == "number") return r.children[n] = e.type === "imageReference" ? {
					type: "image",
					url: i.url,
					title: i.title,
					alt: e.alt
				} : {
					type: "link",
					url: i.url,
					title: i.title,
					children: e.children
				}, [a, n];
			}
		});
	};
}
// node_modules/.pnpm/@milkdown+preset-commonmark@7.23.0/node_modules/@milkdown/preset-commonmark/lib/index.js
function ge(e) {
	let t = { ...e };
	return t.position &&= {
		start: { ...t.position.start },
		end: { ...t.position.end }
	}, delete t.children, t;
}
var _e = "<br />", ve = /^<br\s*\/?\s*>$/i, ye = (e) => typeof e == "string" && ve.test(e.trim());
function be(e, t) {
	if (!(t.childCount >= 1 && t.lastChild?.type.name === "hardbreak")) {
		e.next(t.content);
		return;
	}
	let n = [];
	t.content.forEach((e, r, i) => {
		i !== t.childCount - 1 && n.push(e);
	}), e.next(l.fromArray(n));
}
function k(e, t) {
	return Object.assign(e, { meta: {
		package: "@milkdown/preset-commonmark",
		...t
	} }), e;
}
var xe = C("emphasis");
k(xe, {
	displayName: "Attr<emphasis>",
	group: "Emphasis"
});
var A = b("emphasis", (t) => ({
	attrs: { marker: {
		default: t.get(e).emphasis || "*",
		validate: "string"
	} },
	parseDOM: [
		{ tag: "i" },
		{ tag: "em" },
		{
			style: "font-style",
			getAttrs: (e) => e === "italic"
		}
	],
	toDOM: (e) => ["em", t.get(xe.key)(e)],
	parseMarkdown: {
		match: (e) => e.type === "emphasis",
		runner: (e, t, n) => {
			e.openMark(n, { marker: t.marker }), e.next(t.children), e.closeMark(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "emphasis",
		runner: (e, t) => {
			e.withMark(t, "emphasis", void 0, { marker: t.attrs.marker });
		}
	}
}));
k(A.mark, {
	displayName: "MarkSchema<emphasis>",
	group: "Emphasis"
}), k(A.ctx, {
	displayName: "MarkSchemaCtx<emphasis>",
	group: "Emphasis"
});
var Se = D("ToggleEmphasis", (e) => () => ae(A.type(e)));
k(Se, {
	displayName: "Command<toggleEmphasisCommand>",
	group: "Emphasis"
});
var Ce = T((e) => t(/(?:^|[^*])\*([^*]+)\*$/, A.type(e), {
	getAttr: () => ({ marker: "*" }),
	updateCaptured: ({ fullMatch: e, start: t }) => e.startsWith("*") ? {} : {
		fullMatch: e.slice(1),
		start: t + 1
	}
}));
k(Ce, {
	displayName: "InputRule<emphasis>|Star",
	group: "Emphasis"
});
var we = T((e) => t(/\b_(?![_\s])(.*?[^_\s])_$/, A.type(e), {
	getAttr: () => ({ marker: "_" }),
	updateCaptured: ({ fullMatch: e, start: t }) => e.startsWith("_") ? {} : {
		fullMatch: e.slice(1),
		start: t + 1
	}
}));
k(we, {
	displayName: "InputRule<emphasis>|Underscore",
	group: "Emphasis"
});
var j = w("emphasisKeymap", { ToggleEmphasis: {
	shortcuts: "Mod-i",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(Se.key);
	}
} });
k(j.ctx, {
	displayName: "KeymapCtx<emphasis>",
	group: "Emphasis"
}), k(j.shortcuts, {
	displayName: "Keymap<emphasis>",
	group: "Emphasis"
});
var Te = C("strong");
k(Te, {
	displayName: "Attr<strong>",
	group: "Strong"
});
var M = b("strong", (t) => ({
	attrs: { marker: {
		default: t.get(e).strong || "*",
		validate: "string"
	} },
	parseDOM: [
		{
			tag: "b",
			getAttrs: (e) => e.style.fontWeight != "normal" && null
		},
		{ tag: "strong" },
		{
			style: "font-style",
			getAttrs: (e) => e === "bold"
		},
		{
			style: "font-weight=400",
			clearMark: (e) => e.type.name == "strong"
		},
		{
			style: "font-weight",
			getAttrs: (e) => /^(bold(er)?|[5-9]\d{2,})$/.test(e) && null
		}
	],
	toDOM: (e) => ["strong", t.get(Te.key)(e)],
	parseMarkdown: {
		match: (e) => e.type === "strong",
		runner: (e, t, n) => {
			e.openMark(n, { marker: t.marker }), e.next(t.children), e.closeMark(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "strong",
		runner: (e, t) => {
			e.withMark(t, "strong", void 0, { marker: t.attrs.marker });
		}
	}
}));
k(M.mark, {
	displayName: "MarkSchema<strong>",
	group: "Strong"
}), k(M.ctx, {
	displayName: "MarkSchemaCtx<strong>",
	group: "Strong"
});
var N = D("ToggleStrong", (e) => () => ae(M.type(e)));
k(N, {
	displayName: "Command<toggleStrongCommand>",
	group: "Strong"
});
var Ee = T((e) => t(/(?<![\w:/])(?:\*\*|__)([^*_]+?)(?:\*\*|__)(?![\w/])$/, M.type(e), { getAttr: (e) => ({ marker: e[0].startsWith("*") ? "*" : "_" }) }));
k(Ee, {
	displayName: "InputRule<strong>",
	group: "Strong"
});
var P = w("strongKeymap", { ToggleBold: {
	shortcuts: ["Mod-b"],
	command: (e) => {
		let t = e.get(g);
		return () => t.call(N.key);
	}
} });
k(P.ctx, {
	displayName: "KeymapCtx<strong>",
	group: "Strong"
}), k(P.shortcuts, {
	displayName: "Keymap<strong>",
	group: "Strong"
});
var De = C("inlineCode");
k(De, {
	displayName: "Attr<inlineCode>",
	group: "InlineCode"
});
var F = b("inlineCode", (e) => ({
	priority: 100,
	code: !0,
	inclusive: !1,
	parseDOM: [{ tag: "code" }],
	toDOM: (t) => ["code", e.get(De.key)(t)],
	parseMarkdown: {
		match: (e) => e.type === "inlineCode",
		runner: (e, t, n) => {
			e.openMark(n), e.addText(t.value), e.closeMark(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "inlineCode",
		runner: (e, t, n) => (e.withMark(t, "inlineCode", n.text || ""), !0)
	}
}));
k(F.mark, {
	displayName: "MarkSchema<inlineCode>",
	group: "InlineCode"
}), k(F.ctx, {
	displayName: "MarkSchemaCtx<inlineCode>",
	group: "InlineCode"
});
var I = D("ToggleInlineCode", (e) => () => (t, n) => {
	let { selection: r, tr: i } = t;
	if (r.empty) return !1;
	let { from: a, to: o } = r;
	return t.doc.rangeHasMark(a, o, F.type(e)) ? (n?.(i.removeMark(a, o, F.type(e))), !0) : (Object.keys(t.schema.marks).filter((e) => e !== F.type.name).map((e) => t.schema.marks[e]).forEach((e) => {
		i.removeMark(a, o, e);
	}), n?.(i.addMark(a, o, F.type(e).create())), !0);
});
k(I, {
	displayName: "Command<toggleInlineCodeCommand>",
	group: "InlineCode"
});
var Oe = T((e) => t(/(?:`)([^`]+)(?:`)$/, F.type(e)));
k(Oe, {
	displayName: "InputRule<inlineCodeInputRule>",
	group: "InlineCode"
});
var L = w("inlineCodeKeymap", { ToggleInlineCode: {
	shortcuts: "Mod-e",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(I.key);
	}
} });
k(L.ctx, {
	displayName: "KeymapCtx<inlineCode>",
	group: "InlineCode"
}), k(L.shortcuts, {
	displayName: "Keymap<inlineCode>",
	group: "InlineCode"
});
var ke = /* @__PURE__ */ new Set([
	"http:",
	"https:",
	"mailto:",
	"tel:",
	"ftp:"
]);
function Ae(e) {
	return e <= 32 || e >= 127 && e <= 160 || e >= 8203 && e <= 8205 || e === 8232 || e === 8233 || e === 65279;
}
function je(e) {
	let t = "";
	for (let n of e) Ae(n.codePointAt(0) ?? 0) || (t += n);
	return t;
}
function Me(e) {
	if (typeof e != "string") return "";
	let t = e.trim();
	if (!t) return "";
	let n = je(t), r = /^([a-z][a-z0-9+.-]*):/i.exec(n)?.[1];
	return !r || ke.has(`${r.toLowerCase()}:`) ? t : "";
}
var Ne = C("link");
k(Ne, {
	displayName: "Attr<link>",
	group: "Link"
});
var R = b("link", (e) => ({
	attrs: {
		href: { validate: "string" },
		title: {
			default: null,
			validate: "string|null"
		}
	},
	parseDOM: [{
		tag: "a[href]",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return {
				href: e.getAttribute("href"),
				title: e.getAttribute("title")
			};
		}
	}],
	toDOM: (t) => ["a", {
		...e.get(Ne.key)(t),
		...t.attrs,
		href: Me(t.attrs.href)
	}],
	parseMarkdown: {
		match: (e) => e.type === "link",
		runner: (e, t, n) => {
			let r = t.url, i = t.title;
			e.openMark(n, {
				href: r,
				title: i
			}), e.next(t.children), e.closeMark(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "link",
		runner: (e, t) => {
			e.withMark(t, "link", void 0, {
				title: t.attrs.title,
				url: t.attrs.href
			});
		}
	}
}));
k(R.mark, {
	displayName: "MarkSchema<link>",
	group: "Link"
});
var Pe = D("ToggleLink", (e) => (t = {}) => ae(R.type(e), t));
k(Pe, {
	displayName: "Command<toggleLinkCommand>",
	group: "Link"
});
var Fe = D("UpdateLink", (e) => (t = {}) => (n, r) => {
	if (!r) return !1;
	let i, a = -1, { selection: o } = n, { from: s, to: c } = o;
	if (n.doc.nodesBetween(s, s === c ? c + 1 : c, (t, n) => {
		if (R.type(e).isInSet(t.marks)) return i = t, a = n, !1;
	}), !i) return !1;
	let l = i.marks.find(({ type: t }) => t === R.type(e));
	if (!l) return !1;
	let u = a, d = a + i.nodeSize, { tr: f } = n, ee = R.type(e).create({
		...l.attrs,
		...t
	});
	return ee ? (r(f.removeMark(u, d, l).addMark(u, d, ee).setSelection(new y(f.selection.$anchor)).scrollIntoView()), !0) : !1;
});
k(Fe, {
	displayName: "Command<updateLinkCommand>",
	group: "Link"
});
var Ie = le("doc", () => ({
	content: "block+",
	parseMarkdown: {
		match: ({ type: e }) => e === "root",
		runner: (e, t, n) => {
			e.injectRoot(t, n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "doc",
		runner: (e, t) => {
			e.openNode("root");
			let n = t.childCount > 0 ? t.child(t.childCount - 1) : null, r = n?.type.name === "paragraph" && n.content.size === 0 ? t.content.cut(0, t.content.size - n.nodeSize) : t.content;
			e.next(r);
		}
	}
}));
k(Ie, {
	displayName: "NodeSchema<doc>",
	group: "Doc"
});
var Le = Object.freeze([
	"root",
	"blockquote",
	"listItem",
	"footnoteDefinition"
]), Re = /* @__PURE__ */ new Set([
	"paragraph",
	"heading",
	"tableCell",
	"link",
	"linkReference",
	"emphasis",
	"strong",
	"delete",
	"footnote"
]), z = ce(Le, "blockContainerTypes");
k(z, {
	displayName: "Ctx<blockContainerTypes>",
	group: "Remark"
});
var ze = /* @__PURE__ */ new Set();
function Be(e) {
	let t = e.isInjected(z.key) ? e.get(z.key) : Le, n = /* @__PURE__ */ new Set();
	for (let e of t) Re.has(e) ? ze.has(e) || (ze.add(e), console.warn(`[milkdown/preset-commonmark] "${e}" holds phrasing content and cannot be a block container; it is ignored in blockContainerTypes.`)) : n.add(e);
	return n;
}
var Ve = /* @__PURE__ */ new Set(["paragraph", "tableCell"]);
function He(e, t) {
	return o(e, (e) => e.type === "html" && ye(e.value), (e, n) => {
		let r = n[n.length - 1];
		if (r) {
			if (Ve.has(r.type)) r.children.length === 1 && (r.children = []);
			else if (t.has(r.type)) {
				let t = e;
				return t.children = [], delete t.value, t.type = "paragraph", a;
			}
		}
	});
}
var B = S("remark-preserve-empty-line", (e) => () => (t) => He(t, Be(e)));
k(B.plugin, {
	displayName: "Remark<remarkPreserveEmptyLine>",
	group: "Remark"
}), k(B.options, {
	displayName: "RemarkConfig<remarkPreserveEmptyLine>",
	group: "Remark"
});
var Ue = E("paragraph");
k(Ue, {
	displayName: "Attr<paragraph>",
	group: "Paragraph"
});
var V = x("paragraph", (e) => ({
	content: "inline*",
	group: "block",
	parseDOM: [{ tag: "p" }],
	toDOM: (t) => [
		"p",
		e.get(Ue.key)(t),
		0
	],
	parseMarkdown: {
		match: (e) => e.type === "paragraph",
		runner: (e, t, n) => {
			e.openNode(n), t.children ? e.next(t.children) : e.addText(t.value || ""), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "paragraph",
		runner: (t, n) => {
			t.openNode("paragraph"), (!n.content || n.content.size === 0) && We(e) ? t.addNode("html", void 0, _e) : be(t, n), t.closeNode();
		}
	}
}));
function We(e) {
	return e.isInjected(B.options.key);
}
k(V.node, {
	displayName: "NodeSchema<paragraph>",
	group: "Paragraph"
}), k(V.ctx, {
	displayName: "NodeSchemaCtx<paragraph>",
	group: "Paragraph"
});
var Ge = D("TurnIntoText", (e) => () => p(V.type(e)));
k(Ge, {
	displayName: "Command<turnIntoTextCommand>",
	group: "Paragraph"
});
var Ke = w("paragraphKeymap", { TurnIntoText: {
	shortcuts: "Mod-Alt-0",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(Ge.key);
	}
} });
k(Ke.ctx, {
	displayName: "KeymapCtx<paragraph>",
	group: "Paragraph"
}), k(Ke.shortcuts, {
	displayName: "Keymap<paragraph>",
	group: "Paragraph"
});
var qe = [
	,
	,
	,
	,
	,
	,
].fill(0).map((e, t) => t + 1);
function Je(e) {
	return e.textContent.toLowerCase().trim().replace(/\s+/g, "-");
}
var H = ce(Je, "headingIdGenerator");
k(H, {
	displayName: "Ctx<HeadingIdGenerator>",
	group: "Heading"
});
var Ye = E("heading");
k(Ye, {
	displayName: "Attr<heading>",
	group: "Heading"
});
var U = x("heading", (e) => {
	let t = e.get(H.key);
	return {
		content: "inline*",
		group: "block",
		defining: !0,
		attrs: {
			id: {
				default: "",
				validate: "string"
			},
			level: {
				default: 1,
				validate: "number"
			}
		},
		parseDOM: qe.map((e) => ({
			tag: `h${e}`,
			getAttrs: (t) => {
				if (!(t instanceof HTMLElement)) throw m(t);
				return {
					level: e,
					id: t.id
				};
			}
		})),
		toDOM: (n) => [
			`h${n.attrs.level}`,
			{
				...e.get(Ye.key)(n),
				id: n.attrs.id || t(n)
			},
			0
		],
		parseMarkdown: {
			match: ({ type: e }) => e === "heading",
			runner: (e, t, n) => {
				let r = t.depth;
				e.openNode(n, { level: r }), e.next(t.children), e.closeNode();
			}
		},
		toMarkdown: {
			match: (e) => e.type.name === "heading",
			runner: (e, t) => {
				e.openNode("heading", void 0, { depth: t.attrs.level }), be(e, t), e.closeNode();
			}
		}
	};
});
k(U.node, {
	displayName: "NodeSchema<heading>",
	group: "Heading"
}), k(U.ctx, {
	displayName: "NodeSchemaCtx<heading>",
	group: "Heading"
});
var Xe = T((e) => d(/^(?<hashes>#+)\s$/, U.type(e), (t) => {
	let n = t.groups?.hashes?.length || 0, { $from: r } = e.get(ne).state.selection, i = r.node();
	if (i.type.name === "heading") {
		let e = Number(i.attrs.level) + Number(n);
		return e > 6 && (e = 6), { level: e };
	}
	return { level: n };
}));
k(Xe, {
	displayName: "InputRule<wrapInHeadingInputRule>",
	group: "Heading"
});
var W = D("WrapInHeading", (e) => (t) => (t ??= 1, t < 1 ? p(V.type(e)) : p(U.type(e), { level: t })));
k(W, {
	displayName: "Command<wrapInHeadingCommand>",
	group: "Heading"
});
var Ze = D("DowngradeHeading", (e) => () => (t, n, r) => {
	let { $from: i } = t.selection, a = i.node();
	if (a.type !== U.type(e) || !t.selection.empty || i.parentOffset !== 0) return !1;
	let o = a.attrs.level - 1;
	return o ? (n?.(t.tr.setNodeMarkup(t.selection.$from.before(), void 0, {
		...a.attrs,
		level: o
	})), !0) : p(V.type(e))(t, n, r);
});
k(Ze, {
	displayName: "Command<downgradeHeadingCommand>",
	group: "Heading"
});
var Qe = w("headingKeymap", {
	TurnIntoH1: {
		shortcuts: "Mod-Alt-1",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 1);
		}
	},
	TurnIntoH2: {
		shortcuts: "Mod-Alt-2",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 2);
		}
	},
	TurnIntoH3: {
		shortcuts: "Mod-Alt-3",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 3);
		}
	},
	TurnIntoH4: {
		shortcuts: "Mod-Alt-4",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 4);
		}
	},
	TurnIntoH5: {
		shortcuts: "Mod-Alt-5",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 5);
		}
	},
	TurnIntoH6: {
		shortcuts: "Mod-Alt-6",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(W.key, 6);
		}
	},
	DowngradeHeading: {
		shortcuts: ["Delete", "Backspace"],
		command: (e) => {
			let t = e.get(g);
			return () => t.call(Ze.key);
		}
	}
});
k(Qe.ctx, {
	displayName: "KeymapCtx<heading>",
	group: "Heading"
}), k(Qe.shortcuts, {
	displayName: "Keymap<heading>",
	group: "Heading"
});
var $e = E("blockquote");
k($e, {
	displayName: "Attr<blockquote>",
	group: "Blockquote"
});
var G = x("blockquote", (e) => ({
	content: "block+",
	group: "block",
	defining: !0,
	parseDOM: [{ tag: "blockquote" }],
	toDOM: (t) => [
		"blockquote",
		e.get($e.key)(t),
		0
	],
	parseMarkdown: {
		match: ({ type: e }) => e === "blockquote",
		runner: (e, t, n) => {
			e.openNode(n).next(t.children).closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "blockquote",
		runner: (e, t) => {
			e.openNode("blockquote").next(t.content).closeNode();
		}
	}
}));
k(G.node, {
	displayName: "NodeSchema<blockquote>",
	group: "Blockquote"
}), k(G.ctx, {
	displayName: "NodeSchemaCtx<blockquote>",
	group: "Blockquote"
});
var et = T((e) => u(/^\s*>\s$/, G.type(e)));
k(et, {
	displayName: "InputRule<wrapInBlockquoteInputRule>",
	group: "Blockquote"
});
var tt = D("WrapInBlockquote", (e) => () => ie(G.type(e)));
k(tt, {
	displayName: "Command<wrapInBlockquoteCommand>",
	group: "Blockquote"
});
var nt = w("blockquoteKeymap", { WrapInBlockquote: {
	shortcuts: "Mod-Shift-b",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(tt.key);
	}
} });
k(nt.ctx, {
	displayName: "KeymapCtx<blockquote>",
	group: "Blockquote"
}), k(nt.shortcuts, {
	displayName: "Keymap<blockquote>",
	group: "Blockquote"
});
var rt = E("codeBlock", () => ({
	pre: {},
	code: {}
}));
k(rt, {
	displayName: "Attr<codeBlock>",
	group: "CodeBlock"
});
var K = x("code_block", (e) => ({
	content: "text*",
	group: "block",
	marks: "",
	defining: !0,
	code: !0,
	attrs: { language: {
		default: "",
		validate: "string"
	} },
	parseDOM: [{
		tag: "pre",
		preserveWhitespace: "full",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return { language: e.dataset.language };
		}
	}],
	toDOM: (t) => {
		let n = e.get(rt.key)(t), r = t.attrs.language, i = r && r.length > 0 ? { "data-language": r } : void 0;
		return [
			"pre",
			{
				...n.pre,
				...i
			},
			[
				"code",
				n.code,
				0
			]
		];
	},
	parseMarkdown: {
		match: ({ type: e }) => e === "code",
		runner: (e, t, n) => {
			let r = t.lang ?? "", i = t.value;
			e.openNode(n, { language: r }), i && e.addText(i), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "code_block",
		runner: (e, t) => {
			e.addNode("code", void 0, t.content.firstChild?.text || "", { lang: t.attrs.language });
		}
	}
}));
k(K.node, {
	displayName: "NodeSchema<codeBlock>",
	group: "CodeBlock"
}), k(K.ctx, {
	displayName: "NodeSchemaCtx<codeBlock>",
	group: "CodeBlock"
});
var it = T((e) => d(/^```(?<language>[a-z]*)?[\s\n]$/, K.type(e), (e) => ({ language: e.groups?.language ?? "" })));
k(it, {
	displayName: "InputRule<createCodeBlockInputRule>",
	group: "CodeBlock"
});
var at = D("CreateCodeBlock", (e) => (t = "") => p(K.type(e), { language: t }));
k(at, {
	displayName: "Command<createCodeBlockCommand>",
	group: "CodeBlock"
}), k(D("UpdateCodeBlockLanguage", () => ({ pos: e, language: t } = {
	pos: -1,
	language: ""
}) => (n, r) => e >= 0 && (r?.(n.tr.setNodeAttribute(e, "language", t)), !0)), {
	displayName: "Command<updateCodeBlockLanguageCommand>",
	group: "CodeBlock"
});
var ot = w("codeBlockKeymap", { CreateCodeBlock: {
	shortcuts: "Mod-Alt-c",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(at.key);
	}
} });
k(ot.ctx, {
	displayName: "KeymapCtx<codeBlock>",
	group: "CodeBlock"
}), k(ot.shortcuts, {
	displayName: "Keymap<codeBlock>",
	group: "CodeBlock"
});
var st = E("image");
k(st, {
	displayName: "Attr<image>",
	group: "Image"
});
var q = x("image", (e) => ({
	inline: !0,
	group: "inline",
	selectable: !0,
	draggable: !0,
	marks: "",
	atom: !0,
	defining: !0,
	isolating: !0,
	attrs: {
		src: {
			default: "",
			validate: "string"
		},
		alt: {
			default: "",
			validate: "string"
		},
		title: {
			default: "",
			validate: "string"
		}
	},
	parseDOM: [{
		tag: "img[src]",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return {
				src: e.getAttribute("src") || "",
				alt: e.getAttribute("alt") || "",
				title: e.getAttribute("title") || e.getAttribute("alt") || ""
			};
		}
	}],
	toDOM: (t) => ["img", {
		...e.get(st.key)(t),
		...t.attrs
	}],
	parseMarkdown: {
		match: ({ type: e }) => e === "image",
		runner: (e, t, n) => {
			let r = t.url, i = t.alt ?? "", a = t.title ?? "";
			e.addNode(n, {
				src: r,
				alt: i,
				title: a
			});
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "image",
		runner: (e, t) => {
			e.addNode("image", void 0, void 0, {
				title: t.attrs.title,
				url: t.attrs.src,
				alt: t.attrs.alt
			});
		}
	}
}));
k(q.node, {
	displayName: "NodeSchema<image>",
	group: "Image"
}), k(q.ctx, {
	displayName: "NodeSchemaCtx<image>",
	group: "Image"
});
var ct = D("InsertImage", (e) => (t = {}) => (n, r) => {
	if (!r) return !0;
	let { src: i = "", alt: a = "", title: o = "" } = t, s = q.type(e).create({
		src: i,
		alt: a,
		title: o
	});
	return s && r(n.tr.replaceSelectionWith(s).scrollIntoView()), !0;
});
k(ct, {
	displayName: "Command<insertImageCommand>",
	group: "Image"
});
var lt = D("UpdateImage", (e) => (t = {}) => (r, i) => {
	let a = n(r.selection, q.type(e));
	if (!a) return !1;
	let { node: o, pos: s } = a, c = { ...o.attrs }, { src: l, alt: u, title: d } = t;
	return l !== void 0 && (c.src = l), u !== void 0 && (c.alt = u), d !== void 0 && (c.title = d), i?.(r.tr.setNodeMarkup(s, void 0, c).scrollIntoView()), !0;
});
k(lt, {
	displayName: "Command<updateImageCommand>",
	group: "Image"
}), k(T((e) => new r(/!\[(?<alt>.*?)]\((?<filename>.*?)\s*(?="|\))"?(?<title>[^"]+)?"?\)/, (t, n, r, i) => {
	let [a, o, s = "", c] = n;
	return a ? t.tr.replaceWith(r, i, q.type(e).create({
		src: s,
		alt: o,
		title: c
	})) : null;
})), {
	displayName: "InputRule<insertImageInputRule>",
	group: "Image"
});
var J = E("hardbreak", (e) => ({
	"data-type": "hardbreak",
	"data-is-inline": e.attrs.isInline
}));
k(J, {
	displayName: "Attr<hardbreak>",
	group: "Hardbreak"
});
var Y = x("hardbreak", (e) => ({
	inline: !0,
	group: "inline",
	attrs: { isInline: {
		default: !1,
		validate: "boolean"
	} },
	selectable: !1,
	parseDOM: [{ tag: "br" }, {
		tag: "span[data-type=\"hardbreak\"]",
		getAttrs: () => ({ isInline: !0 })
	}],
	toDOM: (t) => t.attrs.isInline ? [
		"span",
		e.get(J.key)(t),
		" "
	] : ["br", e.get(J.key)(t)],
	parseMarkdown: {
		match: ({ type: e }) => e === "break",
		runner: (e, t, n) => {
			e.addNode(n, { isInline: !!t.data?.isInline });
		}
	},
	leafText: () => "\n",
	toMarkdown: {
		match: (e) => e.type.name === "hardbreak",
		runner: (e, t) => {
			t.attrs.isInline ? e.addNode("text", void 0, "\n") : e.addNode("break");
		}
	}
}));
k(Y.node, {
	displayName: "NodeSchema<hardbreak>",
	group: "Hardbreak"
}), k(Y.ctx, {
	displayName: "NodeSchemaCtx<hardbreak>",
	group: "Hardbreak"
});
var ut = D("InsertHardbreak", (e) => () => (t, n) => {
	let { selection: r, tr: i } = t;
	if (!(r instanceof y)) return !1;
	if (r.empty) {
		let e = r.$from.node();
		if (e.childCount > 0 && e.lastChild?.type.name === "hardbreak") return n?.(i.replaceRangeWith(r.to - 1, r.to, t.schema.node("paragraph")).setSelection(f.near(i.doc.resolve(r.to))).scrollIntoView()), !0;
	}
	return n?.(i.setMeta("hardbreak", !0).replaceSelectionWith(Y.type(e).create(), !1).scrollIntoView()), !0;
});
k(ut, {
	displayName: "Command<insertHardbreakCommand>",
	group: "Hardbreak"
});
var dt = w("hardbreakKeymap", { InsertHardbreak: {
	shortcuts: "Shift-Enter",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(ut.key);
	}
} });
k(dt.ctx, {
	displayName: "KeymapCtx<hardbreak>",
	group: "Hardbreak"
}), k(dt.shortcuts, {
	displayName: "Keymap<hardbreak>",
	group: "Hardbreak"
});
var ft = E("hr");
k(ft, {
	displayName: "Attr<hr>",
	group: "Hr"
});
var X = x("hr", (e) => ({
	group: "block",
	parseDOM: [{ tag: "hr" }],
	toDOM: (t) => ["hr", e.get(ft.key)(t)],
	parseMarkdown: {
		match: ({ type: e }) => e === "thematicBreak",
		runner: (e, t, n) => {
			e.addNode(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "hr",
		runner: (e) => {
			e.addNode("thematicBreak");
		}
	}
}));
k(X.node, {
	displayName: "NodeSchema<hr>",
	group: "Hr"
}), k(X.ctx, {
	displayName: "NodeSchemaCtx<hr>",
	group: "Hr"
});
var pt = T((e) => new r(/^(?:---|___\s|\*\*\*\s)$/, (t, n, r, i) => {
	let { tr: a } = t;
	return n[0] && a.replaceWith(r - 1, i, X.type(e).create()), a;
}));
k(pt, {
	displayName: "InputRule<insertHrInputRule>",
	group: "Hr"
});
var mt = D("InsertHr", (e) => () => (t, n) => {
	if (!n) return !0;
	let r = V.node.type(e).create(), { tr: i, selection: a } = t, { from: o } = a, s = X.type(e).create();
	if (!s) return !0;
	let c = i.replaceSelectionWith(s).insert(o, r), l = f.findFrom(c.doc.resolve(o), 1, !0);
	return l && n(c.setSelection(l).scrollIntoView()), !0;
});
k(mt, {
	displayName: "Command<insertHrCommand>",
	group: "Hr"
});
var ht = E("bulletList");
k(ht, {
	displayName: "Attr<bulletList>",
	group: "BulletList"
});
var Z = x("bullet_list", (e) => ({
	content: "listItem+",
	group: "block",
	attrs: { spread: {
		default: !1,
		validate: "boolean"
	} },
	parseDOM: [{
		tag: "ul",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return { spread: e.dataset.spread === "true" };
		}
	}],
	toDOM: (t) => [
		"ul",
		{
			...e.get(ht.key)(t),
			"data-spread": t.attrs.spread
		},
		0
	],
	parseMarkdown: {
		match: ({ type: e, ordered: t }) => e === "list" && !t,
		runner: (e, t, n) => {
			let r = t.spread ?? !1;
			e.openNode(n, { spread: r }).next(t.children).closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "bullet_list",
		runner: (e, t) => {
			e.openNode("list", void 0, {
				ordered: !1,
				spread: t.attrs.spread
			}).next(t.content).closeNode();
		}
	}
}));
k(Z.node, {
	displayName: "NodeSchema<bulletList>",
	group: "BulletList"
}), k(Z.ctx, {
	displayName: "NodeSchemaCtx<bulletList>",
	group: "BulletList"
});
var gt = T((e) => u(/^\s*([-+*])\s$/, Z.type(e)));
k(gt, {
	displayName: "InputRule<wrapInBulletListInputRule>",
	group: "BulletList"
});
var _t = D("WrapInBulletList", (e) => () => ie(Z.type(e)));
k(_t, {
	displayName: "Command<wrapInBulletListCommand>",
	group: "BulletList"
});
var vt = w("bulletListKeymap", { WrapInBulletList: {
	shortcuts: "Mod-Alt-8",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(_t.key);
	}
} });
k(vt.ctx, {
	displayName: "KeymapCtx<bulletListKeymap>",
	group: "BulletList"
}), k(vt.shortcuts, {
	displayName: "Keymap<bulletListKeymap>",
	group: "BulletList"
});
var yt = E("orderedList");
k(yt, {
	displayName: "Attr<orderedList>",
	group: "OrderedList"
});
var Q = x("ordered_list", (e) => ({
	content: "listItem+",
	group: "block",
	attrs: {
		order: {
			default: 1,
			validate: "number"
		},
		spread: {
			default: !1,
			validate: "boolean"
		}
	},
	parseDOM: [{
		tag: "ol",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return {
				spread: e.dataset.spread === "true",
				order: e.hasAttribute("start") ? Number(e.getAttribute("start")) : 1
			};
		}
	}],
	toDOM: (t) => [
		"ol",
		{
			...e.get(yt.key)(t),
			...t.attrs.order === 1 ? {} : { start: t.attrs.order },
			"data-spread": t.attrs.spread
		},
		0
	],
	parseMarkdown: {
		match: ({ type: e, ordered: t }) => e === "list" && !!t,
		runner: (e, t, n) => {
			let r = t.spread ?? !0;
			e.openNode(n, {
				spread: r,
				order: t.start ?? 1
			}).next(t.children).closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "ordered_list",
		runner: (e, t) => {
			e.openNode("list", void 0, {
				ordered: !0,
				start: t.attrs.order ?? 1,
				spread: t.attrs.spread
			}), e.next(t.content), e.closeNode();
		}
	}
}));
k(Q.node, {
	displayName: "NodeSchema<orderedList>",
	group: "OrderedList"
}), k(Q.ctx, {
	displayName: "NodeSchemaCtx<orderedList>",
	group: "OrderedList"
});
var bt = T((e) => u(/^\s*(\d+)\.\s$/, Q.type(e), (e) => ({ order: Number(e[1]) }), (e, t) => t.childCount + t.attrs.order === Number(e[1])));
k(bt, {
	displayName: "InputRule<wrapInOrderedListInputRule>",
	group: "OrderedList"
});
var xt = D("WrapInOrderedList", (e) => () => ie(Q.type(e)));
k(xt, {
	displayName: "Command<wrapInOrderedListCommand>",
	group: "OrderedList"
});
var St = w("orderedListKeymap", { WrapInOrderedList: {
	shortcuts: "Mod-Alt-7",
	command: (e) => {
		let t = e.get(g);
		return () => t.call(xt.key);
	}
} });
k(St.ctx, {
	displayName: "KeymapCtx<orderedList>",
	group: "OrderedList"
}), k(St.shortcuts, {
	displayName: "Keymap<orderedList>",
	group: "OrderedList"
});
var Ct = E("listItem");
k(Ct, {
	displayName: "Attr<listItem>",
	group: "ListItem"
});
var $ = x("list_item", (e) => ({
	group: "listItem",
	content: "paragraph block*",
	attrs: {
		label: {
			default: "•",
			validate: "string"
		},
		listType: {
			default: "bullet",
			validate: "string"
		},
		spread: {
			default: !0,
			validate: "boolean"
		}
	},
	defining: !0,
	parseDOM: [{
		tag: "li",
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw m(e);
			return {
				label: e.dataset.label,
				listType: e.dataset.listType,
				spread: e.dataset.spread === "true"
			};
		}
	}],
	toDOM: (t) => [
		"li",
		{
			...e.get(Ct.key)(t),
			"data-label": t.attrs.label,
			"data-list-type": t.attrs.listType,
			"data-spread": t.attrs.spread
		},
		0
	],
	parseMarkdown: {
		match: ({ type: e }) => e === "listItem",
		runner: (e, t, n) => {
			let r = t.label == null ? "•" : `${t.label}.`, i = t.label == null ? "bullet" : "ordered", a = t.spread ?? !0;
			e.openNode(n, {
				label: r,
				listType: i,
				spread: a
			}), e.next(t.children), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "list_item",
		runner: (e, t) => {
			e.openNode("listItem", void 0, { spread: t.attrs.spread }), e.next(t.content), e.closeNode();
		}
	}
}));
k($.node, {
	displayName: "NodeSchema<listItem>",
	group: "ListItem"
}), k($.ctx, {
	displayName: "NodeSchemaCtx<listItem>",
	group: "ListItem"
});
var wt = D("SinkListItem", (e) => () => ue($.type(e)));
k(wt, {
	displayName: "Command<sinkListItemCommand>",
	group: "ListItem"
});
var Tt = D("LiftListItem", (e) => () => fe($.type(e)));
k(Tt, {
	displayName: "Command<liftListItemCommand>",
	group: "ListItem"
});
var Et = D("SplitListItem", (e) => () => (t, n, r) => de($.type(e))(t, n && ((e) => {
	let { $from: r } = t.selection;
	if (r.parent.content.size === 0 && r.node(-1).childCount === r.indexAfter(-1)) {
		let { $from: t } = e.selection;
		e.setNodeMarkup(t.before(-1), void 0, r.node(-3).attrs);
	}
	n(e);
}), r));
k(Et, {
	displayName: "Command<splitListItemCommand>",
	group: "ListItem"
});
function Dt(e) {
	return (t, n, r) => {
		let { selection: i } = t;
		if (!(i instanceof y)) return !1;
		let { empty: a, $from: o } = i;
		return !a || o.parentOffset !== 0 || o.node(-1).type !== $.type(e) ? !1 : s(t, n, r);
	};
}
var Ot = D("LiftFirstListItem", (e) => () => Dt(e));
k(Ot, {
	displayName: "Command<liftFirstListItemCommand>",
	group: "ListItem"
});
var kt = w("listItemKeymap", {
	NextListItem: {
		shortcuts: "Enter",
		command: (e) => {
			let t = e.get(g);
			return () => t.call(Et.key);
		}
	},
	SinkListItem: {
		shortcuts: ["Tab", "Mod-]"],
		command: (e) => {
			let t = e.get(g);
			return () => t.call(wt.key);
		}
	},
	LiftListItem: {
		shortcuts: ["Shift-Tab", "Mod-["],
		command: (e) => {
			let t = e.get(g);
			return () => t.call(Tt.key);
		}
	},
	LiftFirstListItem: {
		shortcuts: ["Backspace", "Delete"],
		command: (e) => {
			let t = e.get(g);
			return () => t.call(Ot.key);
		}
	}
});
k(kt.ctx, {
	displayName: "KeymapCtx<listItem>",
	group: "ListItem"
}), k(kt.shortcuts, {
	displayName: "Keymap<listItem>",
	group: "ListItem"
});
var At = le("text", () => ({
	group: "inline",
	parseMarkdown: {
		match: ({ type: e }) => e === "text",
		runner: (e, t) => {
			e.addText(t.value);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "text",
		runner: (e, t) => {
			e.addNode("text", void 0, t.text);
		}
	}
}));
k(At, {
	displayName: "NodeSchema<text>",
	group: "Text"
});
var jt = E("html");
k(jt, {
	displayName: "Attr<html>",
	group: "Html"
});
var Mt = x("html", (e) => ({
	atom: !0,
	group: "inline",
	inline: !0,
	attrs: { value: {
		default: "",
		validate: "string"
	} },
	toDOM: (t) => {
		let n = document.createElement("span"), r = {
			...e.get(jt.key)(t),
			"data-value": t.attrs.value,
			"data-type": "html"
		};
		return n.textContent = t.attrs.value, [
			"span",
			r,
			t.attrs.value
		];
	},
	parseDOM: [{
		tag: "span[data-type=\"html\"]",
		getAttrs: (e) => ({ value: e.dataset.value ?? "" })
	}],
	parseMarkdown: {
		match: ({ type: e }) => e === "html",
		runner: (e, t, n) => {
			e.addNode(n, { value: t.value });
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "html",
		runner: (e, t) => {
			e.addNode("html", void 0, t.attrs.value);
		}
	}
}));
k(Mt.node, {
	displayName: "NodeSchema<html>",
	group: "Html"
}), k(Mt.ctx, {
	displayName: "NodeSchemaCtx<html>",
	group: "Html"
});
var Nt = [
	Ie,
	Ue,
	V,
	H,
	Ye,
	U,
	J,
	Y,
	$e,
	G,
	rt,
	K,
	ft,
	X,
	st,
	q,
	ht,
	Z,
	yt,
	Q,
	Ct,
	$,
	xe,
	A,
	Te,
	M,
	De,
	F,
	Ne,
	R,
	jt,
	Mt,
	At
].flat(), Pt = [
	et,
	gt,
	bt,
	it,
	pt,
	Xe
].flat(), Ft = [
	Ce,
	we,
	Oe,
	Ee
], It = D("IsMarkSelected", () => (e) => (t) => {
	if (!e) return !1;
	let { doc: n, selection: r } = t;
	return n.rangeHasMark(r.from, r.to, e);
}), Lt = D("IsNoteSelected", () => (e) => (t) => e ? c(t, e).hasNode : !1), Rt = D("ClearTextInCurrentBlock", () => () => (e, t) => {
	let n = e.tr, { $from: r, $to: i } = n.selection, { pos: a } = r, { pos: o } = i, s = a - r.node().content.size;
	return s < 0 ? !1 : (n = n.deleteRange(s, o), t?.(n), !0);
}), zt = D("SetBlockType", () => (e) => (t, n) => {
	let { nodeType: r, attrs: i = null } = e ?? {};
	if (!r) return !1;
	let a = t.tr, { from: o, to: s } = a.selection;
	try {
		a.setBlockType(o, s, r, i);
	} catch {
		return !1;
	}
	return n?.(a), !0;
}), Bt = D("WrapInBlockType", () => (e) => (t, n) => {
	let { nodeType: r, attrs: i = null } = e ?? {};
	if (!r) return !1;
	let a = t.tr;
	try {
		let { $from: e, $to: t } = a.selection, n = e.blockRange(t), o = n && oe(n, r, i);
		if (!o) return !1;
		a = a.wrap(n, o);
	} catch {
		return !1;
	}
	return n?.(a), !0;
}), Vt = D("AddBlockType", () => (e) => (t, n) => {
	let { nodeType: r, attrs: i = null } = e ?? {};
	if (!r) return !1;
	let a = t.tr;
	try {
		let e = r instanceof se ? r : r.createAndFill(i);
		if (!e) return !1;
		a.replaceSelectionWith(e);
	} catch {
		return !1;
	}
	return n?.(a), !0;
}), Ht = D("SelectTextNearPos", () => (e) => (t, n) => {
	let { pos: r } = e ?? {};
	if (r == null) return !1;
	let i = (e, t, n) => Math.min(Math.max(e, t), n), a = t.tr;
	try {
		let e = t.doc.resolve(i(r, 0, t.doc.content.size));
		a.setSelection(y.near(e));
	} catch {
		return !1;
	}
	return n?.(a.scrollIntoView()), !0;
}), Ut = [
	Ge,
	tt,
	W,
	Ze,
	at,
	ut,
	mt,
	ct,
	lt,
	xt,
	_t,
	wt,
	Et,
	Tt,
	Ot,
	Se,
	I,
	N,
	Pe,
	Fe,
	It,
	Lt,
	Rt,
	zt,
	Bt,
	Vt,
	Ht
], Wt = [
	nt,
	ot,
	dt,
	Qe,
	kt,
	St,
	vt,
	Ke,
	j,
	L,
	P
].flat(), Gt = S("remarkAddOrderInList", () => () => (e) => {
	h(e, "list", (e) => {
		if (e.ordered) {
			let t = e.start ?? 1;
			e.children.forEach((e, n) => {
				e.label = n + t;
			});
		}
	});
});
k(Gt.plugin, {
	displayName: "Remark<remarkAddOrderInListPlugin>",
	group: "Remark"
}), k(Gt.options, {
	displayName: "RemarkConfig<remarkAddOrderInListPlugin>",
	group: "Remark"
});
var Kt = S("remarkLineBreak", () => () => (e) => {
	let t = /(?<![\t ])[\t ]*(?:\r?\n|\r)/g;
	h(e, "text", (e, n, r) => {
		if (!e.value || typeof e.value != "string") return;
		let i = [], a = 0;
		t.lastIndex = 0;
		let o = t.exec(e.value);
		for (; o;) {
			let n = o.index;
			a !== n && i.push({
				type: "text",
				value: e.value.slice(a, n)
			}), i.push({
				type: "break",
				data: { isInline: !0 }
			}), a = n + o[0].length, o = t.exec(e.value);
		}
		if (i.length > 0 && r && typeof n == "number") return a < e.value.length && i.push({
			type: "text",
			value: e.value.slice(a)
		}), r.children.splice(n, 1, ...i), n + i.length;
	});
});
k(Kt.plugin, {
	displayName: "Remark<remarkLineBreak>",
	group: "Remark"
}), k(Kt.options, {
	displayName: "RemarkConfig<remarkLineBreak>",
	group: "Remark"
});
var qt = S("remarkInlineLink", () => he);
k(qt.plugin, {
	displayName: "Remark<remarkInlineLinkPlugin>",
	group: "Remark"
}), k(qt.options, {
	displayName: "RemarkConfig<remarkInlineLinkPlugin>",
	group: "Remark"
});
var Jt = (e) => !!e.children, Yt = (e) => e.type === "html";
function Xt(e, t) {
	return n(e, 0, null)[0];
	function n(e, r, i) {
		if (Jt(e)) {
			let t = [];
			for (let r = 0, i = e.children.length; r < i; r++) {
				let i = e.children[r];
				if (i) {
					let a = n(i, r, e);
					if (a) for (let e = 0, n = a.length; e < n; e++) {
						let n = a[e];
						n && t.push(n);
					}
				}
			}
			e.children = t;
		}
		return t(e, r, i);
	}
}
var Zt = S("remarkHTMLTransformer", (e) => () => (t) => {
	let n = Be(e);
	Xt(t, (e, t, r) => (Yt(e) && r && n.has(r.type) && (e.children = [ge(e)], delete e.value, e.type = "paragraph"), [e]));
});
k(Zt.plugin, {
	displayName: "Remark<remarkHtmlTransformer>",
	group: "Remark"
}), k(Zt.options, {
	displayName: "RemarkConfig<remarkHtmlTransformer>",
	group: "Remark"
});
var Qt = S("remarkMarker", () => () => (e, t) => {
	let n = (e) => t.value.charAt(e.position.start.offset);
	h(e, (e) => ["strong", "emphasis"].includes(e.type), (e) => {
		e.marker = n(e);
	});
});
k(Qt.plugin, {
	displayName: "Remark<remarkMarker>",
	group: "Remark"
}), k(Qt.options, {
	displayName: "RemarkConfig<remarkMarker>",
	group: "Remark"
});
var $t = O(() => {
	let e = !1, t = new v("MILKDOWN_INLINE_NODES_CURSOR"), n = new _({
		key: t,
		state: {
			init() {
				return !1;
			},
			apply(e) {
				if (!e.selection.empty) return !1;
				let t = e.selection.$from, n = t.nodeBefore, r = t.nodeAfter;
				return !!(n && r && n.isInline && !n.isText && r.isInline && !r.isText);
			}
		},
		props: {
			handleDOMEvents: {
				compositionend: (t, r) => e ? (e = !1, requestAnimationFrame(() => {
					if (n.getState(t.state)) {
						let e = t.state.selection.from;
						r.preventDefault(), t.dispatch(t.state.tr.insertText(r.data || "", e));
					}
				}), !0) : !1,
				compositionstart: (t) => (n.getState(t.state) && (e = !0), !1),
				beforeinput: (t, r) => {
					if (n.getState(t.state) && r instanceof InputEvent && r.data && !e) {
						let e = t.state.selection.from;
						return r.preventDefault(), t.dispatch(t.state.tr.insertText(r.data || "", e)), !0;
					}
					return !1;
				}
			},
			decorations(e) {
				if (n.getState(e)) {
					let t = e.selection.$from.pos, n = document.createElement("span"), r = te.widget(t, n, { side: -1 }), a = document.createElement("span"), o = te.widget(t, a);
					return setTimeout(() => {
						n.contentEditable = "true", a.contentEditable = "true";
					}), i.create(e.doc, [r, o]);
				}
				return i.empty;
			}
		}
	});
	return n;
});
k($t, {
	displayName: "Prose<inlineNodesCursorPlugin>",
	group: "Prose"
});
var en = O((e) => new _({
	key: new v("MILKDOWN_HARDBREAK_MARKS"),
	appendTransaction: (t, n, r) => {
		if (!t.length) return;
		let i = Y.type(e), a;
		return t.forEach((e, n) => {
			e.steps.forEach((e, o) => {
				if (!(e instanceof ee)) return;
				let s = (e, r) => t.slice(n).reduce((e, t, n) => (n === 0 ? t.mapping.slice(o + 1) : t.mapping).map(e, r), e), c = s(e.from, -1), l = s(e.to, 1);
				r.doc.nodesBetween(c, l, (e, t) => {
					e.type !== i || e.attrs.isInline || e.marks.length && (a ??= r.tr, a.setNodeMarkup(t, i, e.attrs, []));
				});
			});
		}), a;
	}
}));
k(en, {
	displayName: "Prose<hardbreakClearMarkPlugin>",
	group: "Prose"
});
var tn = ce(["table", "code_block"], "hardbreakFilterNodes");
k(tn, {
	displayName: "Ctx<hardbreakFilterNodes>",
	group: "Prose"
});
var nn = O((e) => {
	let t = e.get(tn.key);
	return new _({
		key: new v("MILKDOWN_HARDBREAK_FILTER"),
		filterTransaction: (e, n) => {
			let r = e.getMeta("hardbreak"), [i] = e.steps;
			if (r && i instanceof re) {
				let e = n.doc.resolve(i.from), r = e.depth, a = !0;
				for (; r > 0;) t.includes(e.node(r).type.name) && (a = !1), r--;
				return a;
			}
			return !0;
		}
	});
});
k(nn, {
	displayName: "Prose<hardbreakFilterPlugin>",
	group: "Prose"
});
var rn = O((e) => {
	let t = new v("MILKDOWN_HEADING_ID"), n = (n) => {
		if (n.composing) return;
		let r = e.get(H.key), i = n.state.tr.setMeta("addToHistory", !1), a = !1, o = {};
		n.state.doc.descendants((n, s) => {
			if (n.type === U.type(e)) {
				if (n.textContent.trim().length === 0) return;
				let e = n.attrs, c = r(n);
				o[c] ? (o[c] += 1, c += `-#${o[c]}`) : o[c] = 1, e.id !== c && (a = !0, i.setMeta(t, !0).setNodeMarkup(s, void 0, {
					...e,
					id: c
				}));
			}
		}), a && n.dispatch(i);
	};
	return new _({
		key: t,
		view: (e) => (n(e), { update: (e, t) => {
			e.state.doc.eq(t.doc) || n(e);
		} })
	});
});
k(rn, {
	displayName: "Prose<syncHeadingIdPlugin>",
	group: "Prose"
});
var an = O((e) => new _({
	key: new v("MILKDOWN_KEEP_LIST_ORDER"),
	appendTransaction: (t, n, r) => {
		if (!r.selection || t.some((e) => e.getMeta("addToHistory") === !1 || !e.isGeneric)) return null;
		let i = Q.type(e), a = Z.type(e), o = $.type(e), s = (e, t, n = 1) => {
			let r = !1, i = `${t + n}.`;
			return e.label !== i && (e.label = i, r = !0), r;
		}, c = r.tr, l = !1;
		return r.doc.descendants((e, t, n, r) => {
			if (e.type === a) {
				let n = e.maybeChild(0);
				n?.type === o && n.attrs.listType === "ordered" && (l = !0, c.setNodeMarkup(t, i, { spread: !0 }), e.descendants((e, t, n, r) => {
					if (e.type === o) {
						let n = { ...e.attrs };
						s(n, r) && (c = c.setNodeMarkup(t, void 0, n));
					}
					return !1;
				}));
			} else if (e.type === o && n?.type === i) {
				let i = { ...e.attrs }, a = !1;
				i.listType !== "ordered" && (i.listType = "ordered", a = !0), n?.maybeChild(0) && (a = s(i, r, n?.attrs.order ?? 1)), a && (c = c.setNodeMarkup(t, void 0, i), l = !0);
			}
		}), l ? c.setMeta("addToHistory", !1) : null;
	}
}));
k(an, {
	displayName: "Prose<syncListOrderPlugin>",
	group: "Prose"
});
var on = [
	Nt,
	Pt,
	Ft,
	Ut,
	Wt,
	[
		en,
		tn,
		nn,
		$t,
		z,
		Gt,
		qt,
		Kt,
		Zt,
		Qt,
		B,
		rn,
		an
	].flat()
].flat();
export { P as C, N as D, I as E, Bt as O, zt as S, Se as T, $ as _, on as a, Me as b, H as c, q as d, L as f, R as g, Lt as h, K as i, U as l, It as m, G as n, j as o, F as p, Z as r, A as s, Vt as t, X as u, Q as v, M as w, Ht as x, V as y };
