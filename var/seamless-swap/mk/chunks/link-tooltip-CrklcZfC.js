import { At as e, B as t, Bt as n, Ft as r, G as i, Gt as a, Ht as o, J as s, Jt as c, Kt as l, L as u, Lt as d, M as f, Mt as p, Nt as m, Ot as h, Rt as ee, St as g, Ut as te, V as ne, Vt as _, Wt as re, Xt as ie, Yt as v, Zt as y, ct as b, in as x, j as S, jt as ae, l as oe, m as se, ot as ce, q as le, qt as ue, st as de, tt as fe, ut as pe, zt as me } from "./lib-9QpRoHAM.js";
import { _ as he, a as ge, c as C, d as _e, i as ve, l as ye, m as be, n as xe, p as Se, r as Ce, t as w, u as we } from "./lib-DaU_YkmU.js";
import { t as Te } from "./purify.es-CZ6K1J3y.js";
import { _ as T, c as Ee, d as E, g as De, h as Oe, l as ke, o as Ae, s as je, u as Me } from "./floating-ui.dom-DE0KslHM.js";
import { a as Ne, n as Pe, r as Fe } from "./lib-B0HMIP_r.js";
import { _ as Ie, b as Le, g as Re, y as ze } from "./lib-Cs-8MFWq.js";
import { _ as Be, c as Ve, d as He, f as Ue, g as We, h as Ge, i as Ke, l as qe, m as Je, n as Ye, p as Xe, r as Ze, s as Qe, t as $e, u as et, v as tt, y as nt } from "./dist-DPp-l4A4.js";
// node_modules/.pnpm/@milkdown+components@7.23.0_@codemirror+language@6.13.1_@codemirror+state@6.7.6_@codemi_2e95d647a0d9839042dd5c8a016897e3/node_modules/@milkdown/components/lib/image-block/index.js
var rt = Object.defineProperty, it = Object.getOwnPropertySymbols, at = Object.prototype.hasOwnProperty, ot = Object.prototype.propertyIsEnumerable, st = (e, t, n) => t in e ? rt(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, ct = (e, t) => {
	for (var n in t ||= {}) at.call(t, n) && st(e, n, t[n]);
	if (it) for (var n of it(t)) ot.call(t, n) && st(e, n, t[n]);
	return e;
};
function D(e, t) {
	return Object.assign(e, { meta: ct({ package: "@milkdown/components" }, t) }), e;
}
var lt = xe({
	imageIcon: "🌌",
	captionIcon: "💬",
	uploadButton: "Upload file",
	confirmButton: "Confirm ⏎",
	uploadPlaceholderText: "or paste the image link ...",
	captionPlaceholderText: "Image caption",
	onUpload: (e) => Promise.resolve(URL.createObjectURL(e))
}, "imageBlockConfigCtx");
D(lt, {
	displayName: "Config<image-block>",
	group: "ImageBlock"
});
function ut(e) {
	return ae(e, "paragraph", (e, t, n) => {
		if (e.children?.length !== 1) return;
		let r = e.children?.[0];
		if (!r || r.type !== "image") return;
		let { url: i, alt: a, title: o } = r, s = {
			type: "image-block",
			url: i,
			alt: a,
			title: o
		};
		n.children.splice(t, 1, s);
	});
}
var dt = _e("remark-image-block", () => () => ut);
D(dt.plugin, {
	displayName: "Remark<remarkImageBlock>",
	group: "ImageBlock"
}), D(dt.options, {
	displayName: "RemarkConfig<remarkImageBlock>",
	group: "ImageBlock"
});
var ft = Object.defineProperty, pt = Object.getOwnPropertySymbols, mt = Object.prototype.hasOwnProperty, ht = Object.prototype.propertyIsEnumerable, gt = (e, t, n) => t in e ? ft(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, _t = (e, t) => {
	for (var n in t ||= {}) mt.call(t, n) && gt(e, n, t[n]);
	if (pt) for (var n of pt(t)) ht.call(t, n) && gt(e, n, t[n]);
	return e;
}, vt = "image-block", yt = C("image-block", () => ({
	inline: !1,
	group: "block",
	selectable: !0,
	draggable: !0,
	isolating: !0,
	marks: "",
	atom: !0,
	priority: 100,
	attrs: {
		src: {
			default: "",
			validate: "string"
		},
		caption: {
			default: "",
			validate: "string"
		},
		ratio: {
			default: 1,
			validate: "number"
		}
	},
	parseDOM: [{
		tag: `img[data-type="${vt}"]`,
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw x(e);
			return {
				src: e.getAttribute("src") || "",
				caption: e.getAttribute("caption") || "",
				ratio: Number(e.getAttribute("ratio") ?? 1)
			};
		}
	}],
	toDOM: (e) => ["img", _t({ "data-type": vt }, e.attrs)],
	parseMarkdown: {
		match: ({ type: e }) => e === "image-block",
		runner: (e, t, n) => {
			let r = t.url, i = t.title ?? "", a = Number(t.alt || 1);
			(Number.isNaN(a) || a === 0) && (a = 1), e.addNode(n, {
				src: r,
				caption: i,
				ratio: a
			});
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "image-block",
		runner: (e, t) => {
			e.openNode("paragraph"), e.addNode("image", void 0, void 0, {
				title: t.attrs.caption,
				url: t.attrs.src,
				alt: `${Number.parseFloat(t.attrs.ratio).toFixed(2)}`
			}), e.closeNode();
		}
	}
}));
D(yt.node, {
	displayName: "NodeSchema<image-block>",
	group: "ImageBlock"
});
var bt = he("abcdefghijklmnopqrstuvwxyz0123456789", 8);
function xt(e) {
	return `${e}-${bt()}`;
}
function St({ icon: e, class: t, onClick: n }) {
	return /* @__PURE__ */ E("span", {
		class: Ae("milkdown-icon", t),
		onPointerdown: n,
		innerHTML: e ? Te.sanitize(e.trim()) : void 0
	});
}
St.props = {
	icon: {
		type: String,
		required: !1
	},
	class: {
		type: String,
		required: !1
	},
	onClick: {
		type: Function,
		required: !1
	}
};
var Ct = Me({
	props: {
		src: {
			type: Object,
			required: !0
		},
		selected: {
			type: Object,
			required: !0
		},
		readonly: {
			type: Object,
			required: !0
		},
		setLink: {
			type: Function,
			required: !0
		},
		imageIcon: {
			type: String,
			required: !1
		},
		uploadButton: {
			type: String,
			required: !1
		},
		confirmButton: {
			type: String,
			required: !1
		},
		uploadPlaceholderText: {
			type: String,
			required: !1
		},
		onUpload: {
			type: Function,
			required: !0
		},
		onImageLoadError: {
			type: Function,
			required: !1
		}
	},
	setup({ readonly: e, src: t, setLink: n, onUpload: r, imageIcon: i, uploadButton: a, confirmButton: o, uploadPlaceholderText: s, className: c, onImageLoadError: l }) {
		let u = T(!1), d = T(), f = T(t.value ?? ""), p = xt("milkdown-image-link"), m = xt("milkdown-image-upload"), h = T(t.value?.length !== 0), ee = (e) => {
			let t = e.target.value;
			h.value = t.length !== 0, f.value = t;
		}, g = (e) => {
			e.key === "Enter" && n(d.value?.value ?? "");
		}, te = () => {
			n(d.value?.value ?? "");
		}, ne = (e) => {
			let t = e.target.files?.[0];
			t && r(t).then((e) => {
				e && (n(e), h.value = !0);
			}).catch((e) => {
				console.error("An error occurred while uploading image"), console.error(e);
			});
		};
		return () => /* @__PURE__ */ E("div", { class: Ae("image-edit", c) }, /* @__PURE__ */ E(St, {
			icon: i,
			class: "image-icon"
		}), /* @__PURE__ */ E("div", { class: Ae("link-importer", u.value && "focus") }, /* @__PURE__ */ E("input", {
			id: p,
			ref: d,
			draggable: "true",
			onDragstart: (e) => {
				e.preventDefault(), e.stopPropagation();
			},
			disabled: e.value,
			class: "link-input-area",
			value: f.value,
			onInput: ee,
			onKeydown: g,
			onFocus: () => u.value = !0,
			onBlur: () => u.value = !1
		}), !h.value && /* @__PURE__ */ E("div", { class: "placeholder" }, /* @__PURE__ */ E("input", {
			disabled: e.value,
			class: "hidden",
			id: m,
			type: "file",
			accept: "image/*",
			onChange: ne
		}), /* @__PURE__ */ E("label", {
			class: "uploader",
			for: m
		}, /* @__PURE__ */ E(St, { icon: a })), /* @__PURE__ */ E("span", {
			class: "text",
			onClick: () => d.value?.focus()
		}, s))), f.value && /* @__PURE__ */ E(Ee, null, /* @__PURE__ */ E("div", { class: "image-preview" }, /* @__PURE__ */ E("img", {
			src: f.value,
			alt: "",
			onError: (e) => Promise.resolve(l?.(e)).catch(() => {})
		})), /* @__PURE__ */ E("div", {
			class: "confirm",
			onClick: () => te()
		}, /* @__PURE__ */ E(St, { icon: o }))));
	}
}), wt = Me({
	props: {
		src: {
			type: Object,
			required: !0
		},
		caption: {
			type: Object,
			required: !0
		},
		ratio: {
			type: Object,
			required: !0
		},
		selected: {
			type: Object,
			required: !0
		},
		readonly: {
			type: Object,
			required: !0
		},
		setAttr: {
			type: Function,
			required: !0
		},
		config: {
			type: Object,
			required: !0
		}
	},
	setup({ src: e, caption: t, ratio: n, readonly: r, setAttr: i, config: a }) {
		let o = T(), s = T(), c = T(!!t.value?.length), l = xt("milkdown-image-caption"), u = T(0), d = () => {
			let e = o.value;
			if (!e) return;
			let t = e.closest(".milkdown-image-block");
			if (!t) return;
			let r = t.getBoundingClientRect().width;
			if (!r) return;
			a.maxWidth && a.maxWidth < r && (r = a.maxWidth);
			let i = e.naturalHeight, s = e.naturalWidth, c = s < r ? i : i / s * r;
			a.maxHeight && c > a.maxHeight && (c = a.maxHeight);
			let l = (c * (n.value ?? 1)).toFixed(2);
			e.dataset.origin = c.toFixed(2), e.dataset.height = l, e.style.height = `${l}px`, a.maxWidth && (e.style.maxWidth = `${a.maxWidth}px`);
		}, f = (e) => {
			e.preventDefault(), e.stopPropagation(), r.value || (c.value = !c.value);
		}, p = (e) => {
			let t = e.target.value;
			u.value && window.clearTimeout(u.value), u.value = window.setTimeout(() => {
				i("caption", t);
			}, 1e3);
		}, m = (e) => {
			let t = e.target.value;
			u.value &&= (window.clearTimeout(u.value), 0), i("caption", t);
		}, h = (e) => {
			e.preventDefault();
			let t = o.value;
			if (!t) return;
			let n = t.getBoundingClientRect().top, r = e.clientY - n;
			r < 100 && (r = 100), a.maxHeight && r > a.maxHeight && (r = a.maxHeight);
			let i = Number(r).toFixed(2);
			t.dataset.height = i, t.style.height = `${i}px`;
		}, ee = () => {
			window.removeEventListener("pointermove", h), window.removeEventListener("pointerup", ee);
			let e = o.value;
			if (!e) return;
			let t = Number(e.dataset.origin), n = Number(e.dataset.height), r = Number.parseFloat(Number(n / t).toFixed(2));
			Number.isNaN(r) || i("ratio", r);
		}, g = (e) => {
			r.value || (e.preventDefault(), e.stopPropagation(), window.addEventListener("pointermove", h), window.addEventListener("pointerup", ee));
		};
		return () => /* @__PURE__ */ E(Ee, null, /* @__PURE__ */ E("div", { class: "image-wrapper" }, /* @__PURE__ */ E("div", { class: "operation" }, /* @__PURE__ */ E("div", {
			class: "operation-item",
			onPointerdown: f
		}, /* @__PURE__ */ E(St, { icon: a.captionIcon }))), /* @__PURE__ */ E("img", {
			ref: o,
			"data-type": vt,
			onLoad: d,
			src: e.value,
			alt: t.value,
			onError: (e) => Promise.resolve(a.onImageLoadError?.call(a, e)).catch(() => {})
		}), /* @__PURE__ */ E("div", {
			ref: s,
			class: "image-resize-handle",
			onPointerdown: g
		})), c.value && /* @__PURE__ */ E("input", {
			id: l,
			draggable: "true",
			onDragstart: (e) => {
				e.preventDefault(), e.stopPropagation();
			},
			class: "caption-input",
			placeholder: a?.captionPlaceholderText,
			onInput: p,
			onBlur: m,
			value: t.value
		}));
	}
}), Tt = Object.defineProperty, Et = Object.getOwnPropertySymbols, Dt = Object.prototype.hasOwnProperty, Ot = Object.prototype.propertyIsEnumerable, kt = (e, t, n) => t in e ? Tt(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, At = (e, t) => {
	for (var n in t ||= {}) Dt.call(t, n) && kt(e, n, t[n]);
	if (Et) for (var n of Et(t)) Ot.call(t, n) && kt(e, n, t[n]);
	return e;
}, jt = Me({
	props: {
		src: {
			type: Object,
			required: !0
		},
		caption: {
			type: Object,
			required: !0
		},
		ratio: {
			type: Object,
			required: !0
		},
		selected: {
			type: Object,
			required: !0
		},
		readonly: {
			type: Object,
			required: !0
		},
		setAttr: {
			type: Function,
			required: !0
		},
		config: {
			type: Object,
			required: !0
		}
	},
	setup(e) {
		let { src: t } = e;
		return () => t.value?.length ? /* @__PURE__ */ E(wt, At({}, e)) : /* @__PURE__ */ E(Ct, {
			src: e.src,
			selected: e.selected,
			readonly: e.readonly,
			setLink: (t) => e.setAttr("src", t),
			imageIcon: e.config.imageIcon,
			uploadButton: e.config.uploadButton,
			confirmButton: e.config.confirmButton,
			uploadPlaceholderText: e.config.uploadPlaceholderText,
			onUpload: e.config.onUpload,
			onImageLoadError: e.config.onImageLoadError
		});
	}
}), Mt = be(yt.node, (e) => (t, n, r) => {
	let i = T(t.attrs.src), a = T(t.attrs.caption), o = T(t.attrs.ratio), s = T(!1), c = T(!n.editable), l = (e, t) => {
		if (!n.editable) return;
		let i = r();
		i != null && n.dispatch(n.state.tr.setNodeAttribute(i, e, e === "src" ? Te.sanitize(t) : t));
	}, u = e.get(lt.key), d = je(jt, {
		src: i,
		caption: a,
		ratio: o,
		selected: s,
		readonly: c,
		setAttr: l,
		config: u
	}), f = document.createElement("div");
	f.className = "milkdown-image-block";
	let p = De(() => {
		s.value ? f.classList.add("selected") : f.classList.remove("selected");
	}), m = u.proxyDomURL, h = (e) => {
		if (!m) i.value = e.attrs.src;
		else {
			let t = m(e.attrs.src);
			typeof t == "string" ? i.value = t : t.then((e) => {
				i.value = e;
			}).catch(console.error);
		}
		o.value = e.attrs.ratio, a.value = e.attrs.caption, c.value = !n.editable;
	};
	return h(t), d.mount(f), {
		dom: f,
		update: (e) => e.type === t.type && (h(e), !0),
		stopEvent: (e) => e.target instanceof HTMLInputElement,
		selectNode: () => {
			s.value = !0;
		},
		deselectNode: () => {
			s.value = !1;
		},
		destroy: () => {
			p(), d.unmount(), f.remove();
		}
	};
});
D(Mt, {
	displayName: "NodeView<image-block>",
	group: "ImageBlock"
}), [
	dt,
	yt,
	Mt,
	lt
].flat();
// node_modules/.pnpm/prosemirror-safari-ime-span@1.0.2/node_modules/prosemirror-safari-ime-span/dist/index.js
var Nt = typeof navigator < "u" ? navigator : null, Pt = Nt && Nt.userAgent || "", Ft = /Edge\/(\d+)/.exec(Pt), It = /MSIE \d/.exec(Pt), Lt = /Trident\/(?:[7-9]|\d{2,})\..*rv:(\d+)/.exec(Pt), Rt = !(It || Lt || Ft) && !!Nt && /Apple Computer/.test(Nt.vendor), zt = new de("safari-ime-span"), Bt = !1, Vt = {
	key: zt,
	props: {
		decorations: Ht,
		handleDOMEvents: {
			compositionstart: () => {
				Bt = !0;
			},
			compositionend: () => {
				Bt = !1;
			}
		}
	}
};
function Ht(e) {
	let { $from: t, $to: n, to: r } = e.selection;
	if (Bt && t.sameParent(n)) {
		let t = S.widget(r, Ut, {
			ignoreSelection: !0,
			key: "safari-ime-span"
		});
		return f.create(e.doc, [t]);
	}
}
function Ut(e) {
	let t = e.dom.ownerDocument.createElement("span");
	return t.className = "ProseMirror-safari-ime-span", t;
}
var Wt = new ce(Rt ? Vt : { key: zt });
// node_modules/.pnpm/ccount@2.0.1/node_modules/ccount/index.js
function Gt(e, t) {
	let n = String(e);
	if (typeof t != "string") throw TypeError("Expected character");
	let r = 0, i = n.indexOf(t);
	for (; i !== -1;) r++, i = n.indexOf(t, i + t.length);
	return r;
}
// node_modules/.pnpm/escape-string-regexp@5.0.0/node_modules/escape-string-regexp/index.js
function Kt(e) {
	if (typeof e != "string") throw TypeError("Expected a string");
	return e.replace(/[|\\{}()[\]^$+*?.]/g, "\\$&").replace(/-/g, "\\x2d");
}
// node_modules/.pnpm/mdast-util-find-and-replace@3.0.4/node_modules/mdast-util-find-and-replace/lib/index.js
function qt(e, t, n) {
	let i = r((n || {}).ignore || []), a = Jt(t), o = -1;
	for (; ++o < a.length;) m(e, s);
	function s(e, t, n) {
		if (e.type === "text" && typeof n == "number") return c(e, t, n);
		if (i(e, n, t[t.length - 1])) return p;
	}
	function c(e, t, n) {
		let r = t[t.length - 1], i = a[o][0], s = a[o][1], c = 0, l = !1, u = [];
		i.lastIndex = 0;
		let d = i.exec(e.value);
		for (; d;) {
			let n = d.index, r = {
				index: d.index,
				input: d.input,
				stack: [...t, e]
			}, a = s(...d, r);
			if (typeof a == "string" && (a = a.length > 0 ? {
				type: "text",
				value: a
			} : void 0), a === !1) i.lastIndex = n + 1;
			else {
				if (c !== n && f({
					type: "text",
					value: e.value.slice(c, n)
				}), Array.isArray(a)) for (let e of a) f(e);
				else a && f(a);
				c = n + d[0].length, l = !0;
			}
			if (!i.global) break;
			d = i.exec(e.value);
		}
		return l ? (c < e.value.length && f({
			type: "text",
			value: e.value.slice(c)
		}), r.children.splice(n, 1, ...u)) : u = [e], n + u.length;
		function f(e) {
			let t = u[u.length - 1];
			t && t.type === "text" && e.type === "text" ? t.value += e.value : u.push(e);
		}
	}
}
function Jt(e) {
	if (!Array.isArray(e)) throw TypeError("Expected find and replace tuple or list of tuples");
	let t = [], n = !e[0] || Array.isArray(e[0]) ? e : [e], r = -1;
	for (; ++r < n.length;) {
		let e = n[r];
		t.push([Yt(e[0]), Xt(e[1])]);
	}
	return t;
}
function Yt(e) {
	return typeof e == "string" ? new RegExp(Kt(e), "g") : e;
}
function Xt(e) {
	return typeof e == "function" ? e : function() {
		return e;
	};
}
// node_modules/.pnpm/mdast-util-gfm-autolink-literal@2.0.1/node_modules/mdast-util-gfm-autolink-literal/lib/index.js
var Zt = "phrasing", Qt = [
	"autolink",
	"link",
	"image",
	"label"
];
function $t() {
	return {
		transforms: [cn],
		enter: {
			literalAutolink: tn,
			literalAutolinkEmail: nn,
			literalAutolinkHttp: nn,
			literalAutolinkWww: nn
		},
		exit: {
			literalAutolink: sn,
			literalAutolinkEmail: on,
			literalAutolinkHttp: rn,
			literalAutolinkWww: an
		}
	};
}
function en() {
	return { unsafe: [
		{
			character: "@",
			before: "[+\\-.\\w]",
			after: "[\\-.\\w]",
			inConstruct: Zt,
			notInConstruct: Qt
		},
		{
			character: ".",
			before: "[Ww]",
			after: "[\\-.\\w]",
			inConstruct: Zt,
			notInConstruct: Qt
		},
		{
			character: ":",
			before: "[ps]",
			after: "\\/",
			inConstruct: Zt,
			notInConstruct: Qt
		}
	] };
}
function tn(e) {
	this.enter({
		type: "link",
		title: null,
		url: "",
		children: []
	}, e);
}
function nn(e) {
	this.config.enter.autolinkProtocol.call(this, e);
}
function rn(e) {
	this.config.exit.autolinkProtocol.call(this, e);
}
function an(e) {
	this.config.exit.data.call(this, e);
	let t = this.stack[this.stack.length - 1];
	t.type, t.url = "http://" + this.sliceSerialize(e);
}
function on(e) {
	this.config.exit.autolinkEmail.call(this, e);
}
function sn(e) {
	this.exit(e);
}
function cn(e) {
	qt(e, [[/(https?:\/\/|www(?=\.))([-.\w]+)([^ \t\r\n]*)/gi, ln], [/(?<=^|\s|\p{P}|\p{S})([-.\w+]+)@([-\w]+(?:\.[-\w]+)+)/gu, un]], { ignore: ["link", "linkReference"] });
}
function ln(e, t, n, r, i) {
	let a = "";
	if (!pn(i) || (/^w/i.test(t) && (n = t + n, t = "", a = "http://"), !dn(n))) return !1;
	let o = fn(n + r);
	if (!o[0]) return !1;
	let s = {
		type: "link",
		title: null,
		url: a + t + o[0],
		children: [{
			type: "text",
			value: t + o[0]
		}]
	};
	return o[1] ? [s, {
		type: "text",
		value: o[1]
	}] : s;
}
function un(e, t, n, r) {
	return !pn(r, !0) || /[-\d_]$/.test(n) ? !1 : {
		type: "link",
		title: null,
		url: "mailto:" + t + "@" + n,
		children: [{
			type: "text",
			value: t + "@" + n
		}]
	};
}
function dn(e) {
	let t = e.split(".");
	return !(t.length < 2 || t[t.length - 1] && (/_/.test(t[t.length - 1]) || !/[a-zA-Z\d]/.test(t[t.length - 1])) || t[t.length - 2] && (/_/.test(t[t.length - 2]) || !/[a-zA-Z\d]/.test(t[t.length - 2])));
}
function fn(e) {
	let t = /[!"&'),.:;<>?\]}]+$/.exec(e);
	if (!t) return [e, void 0];
	e = e.slice(0, t.index);
	let n = t[0], r = n.indexOf(")"), i = Gt(e, "("), a = Gt(e, ")");
	for (; r !== -1 && i > a;) e += n.slice(0, r + 1), n = n.slice(r + 1), r = n.indexOf(")"), a++;
	return [e, n];
}
function pn(e, t) {
	let n = e.input.charCodeAt(e.index - 1);
	return (e.index === 0 || c(n) || ue(n)) && (!t || n !== 47);
}
// node_modules/.pnpm/mdast-util-gfm-footnote@2.1.0/node_modules/mdast-util-gfm-footnote/lib/index.js
Cn.peek = Sn;
function mn() {
	this.buffer();
}
function hn(e) {
	this.enter({
		type: "footnoteReference",
		identifier: "",
		label: ""
	}, e);
}
function gn() {
	this.buffer();
}
function _n(e) {
	this.enter({
		type: "footnoteDefinition",
		identifier: "",
		label: "",
		children: []
	}, e);
}
function vn(e) {
	let t = this.resume(), n = this.stack[this.stack.length - 1];
	n.type, n.identifier = v(this.sliceSerialize(e)).toLowerCase(), n.label = t;
}
function yn(e) {
	this.exit(e);
}
function bn(e) {
	let t = this.resume(), n = this.stack[this.stack.length - 1];
	n.type, n.identifier = v(this.sliceSerialize(e)).toLowerCase(), n.label = t;
}
function xn(e) {
	this.exit(e);
}
function Sn() {
	return "[";
}
function Cn(e, t, n, r) {
	let i = n.createTracker(r), a = i.move("[^"), o = n.enter("footnoteReference"), s = n.enter("reference");
	return a += i.move(n.safe(n.associationId(e), {
		after: "]",
		before: a
	})), s(), o(), a += i.move("]"), a;
}
function wn() {
	return {
		enter: {
			gfmFootnoteCallString: mn,
			gfmFootnoteCall: hn,
			gfmFootnoteDefinitionLabelString: gn,
			gfmFootnoteDefinition: _n
		},
		exit: {
			gfmFootnoteCallString: vn,
			gfmFootnoteCall: yn,
			gfmFootnoteDefinitionLabelString: bn,
			gfmFootnoteDefinition: xn
		}
	};
}
function Tn(e) {
	let t = !1;
	return e && e.firstLineBlank && (t = !0), {
		handlers: {
			footnoteDefinition: n,
			footnoteReference: Cn
		},
		unsafe: [{
			character: "[",
			inConstruct: [
				"label",
				"phrasing",
				"reference"
			]
		}]
	};
	function n(e, n, r, i) {
		let a = r.createTracker(i), o = a.move("[^"), s = r.enter("footnoteDefinition"), c = r.enter("label");
		return o += a.move(r.safe(r.associationId(e), {
			before: o,
			after: "]"
		})), c(), o += a.move("]:"), e.children && e.children.length > 0 && (a.shift(4), o += a.move((t ? "\n" : " ") + r.indentLines(r.containerFlow(e, a.current()), t ? Dn : En))), s(), o;
	}
}
function En(e, t, n) {
	return t === 0 ? e : Dn(e, t, n);
}
function Dn(e, t, n) {
	return (n ? "" : "    ") + e;
}
// node_modules/.pnpm/mdast-util-gfm-strikethrough@2.0.1/node_modules/mdast-util-gfm-strikethrough/lib/index.js
var On = [
	"autolink",
	"destinationLiteral",
	"destinationRaw",
	"reference",
	"titleQuote",
	"titleApostrophe"
];
Nn.attention = Pn, Nn.peek = Fn;
function kn() {
	return {
		canContainEols: ["delete"],
		enter: { strikethrough: jn },
		exit: { strikethrough: Mn }
	};
}
function An() {
	return {
		handlers: { delete: Nn },
		unsafe: [{
			character: "~",
			inConstruct: "phrasing",
			notInConstruct: On
		}]
	};
}
function jn(e) {
	this.enter({
		type: "delete",
		children: [],
		position: void 0
	}, e);
}
function Mn(e) {
	this.exit(e);
}
function Nn(e, t, n, r) {
	let i = n.createTracker(r), a = n.stack.includes("strikethrough") ? "~" : "~~", o = n.enter("strikethrough"), s = i.move(a);
	return s += n.containerPhrasing(e, {
		...i.current(),
		before: s,
		after: "~"
	}), s += i.move(a), o(), s;
}
function Pn() {
	return {
		construct: "strikethrough",
		markers: ["~"],
		sizes: [2, 1]
	};
}
function Fn() {
	return "~";
}
// node_modules/.pnpm/markdown-table@3.0.4/node_modules/markdown-table/index.js
function In(e) {
	return e.length;
}
function Ln(e, t) {
	let n = t || {}, r = (n.align || []).concat(), i = n.stringLength || In, a = [], o = [], s = [], c = [], l = 0, u = -1;
	for (; ++u < e.length;) {
		let t = [], r = [], a = -1;
		for (e[u].length > l && (l = e[u].length); ++a < e[u].length;) {
			let o = Rn(e[u][a]);
			if (n.alignDelimiters !== !1) {
				let e = i(o);
				r[a] = e, (c[a] === void 0 || e > c[a]) && (c[a] = e);
			}
			t.push(o);
		}
		o[u] = t, s[u] = r;
	}
	let d = -1;
	if (typeof r == "object" && "length" in r) for (; ++d < l;) a[d] = zn(r[d]);
	else {
		let e = zn(r);
		for (; ++d < l;) a[d] = e;
	}
	d = -1;
	let f = [], p = [];
	for (; ++d < l;) {
		let e = a[d], t = "", r = "";
		e === 99 ? (t = ":", r = ":") : e === 108 ? t = ":" : e === 114 && (r = ":");
		let i = n.alignDelimiters === !1 ? 1 : Math.max(1, c[d] - t.length - r.length), o = t + "-".repeat(i) + r;
		n.alignDelimiters !== !1 && (i = t.length + i + r.length, i > c[d] && (c[d] = i), p[d] = i), f[d] = o;
	}
	o.splice(1, 0, f), s.splice(1, 0, p), u = -1;
	let m = [];
	for (; ++u < o.length;) {
		let e = o[u], t = s[u];
		d = -1;
		let r = [];
		for (; ++d < l;) {
			let i = e[d] || "", o = "", s = "";
			if (n.alignDelimiters !== !1) {
				let e = c[d] - (t[d] || 0), n = a[d];
				n === 114 ? o = " ".repeat(e) : n === 99 ? e % 2 ? (o = " ".repeat(e / 2 + .5), s = " ".repeat(e / 2 - .5)) : (o = " ".repeat(e / 2), s = o) : s = " ".repeat(e);
			}
			n.delimiterStart !== !1 && !d && r.push("|"), n.padding === !1 || n.alignDelimiters === !1 && i === "" || n.delimiterStart === !1 && !d || r.push(" "), n.alignDelimiters !== !1 && r.push(o), r.push(i), n.alignDelimiters !== !1 && r.push(s), n.padding !== !1 && r.push(" "), (n.delimiterEnd !== !1 || d !== l - 1) && r.push("|");
		}
		m.push(n.delimiterEnd === !1 ? r.join("").replace(/ +$/, "") : r.join(""));
	}
	return m.join("\n");
}
function Rn(e) {
	return e == null ? "" : String(e);
}
function zn(e) {
	let t = typeof e == "string" ? e.codePointAt(0) : 0;
	return t === 67 || t === 99 ? 99 : t === 76 || t === 108 ? 108 : t === 82 || t === 114 ? 114 : 0;
}
// node_modules/.pnpm/mdast-util-gfm-table@2.0.0/node_modules/mdast-util-gfm-table/lib/index.js
function Bn() {
	return {
		enter: {
			table: Vn,
			tableData: Gn,
			tableHeader: Gn,
			tableRow: Un
		},
		exit: {
			codeText: Kn,
			table: Hn,
			tableData: Wn,
			tableHeader: Wn,
			tableRow: Wn
		}
	};
}
function Vn(e) {
	let t = e._align;
	this.enter({
		type: "table",
		align: t.map(function(e) {
			return e === "none" ? null : e;
		}),
		children: []
	}, e), this.data.inTable = !0;
}
function Hn(e) {
	this.exit(e), this.data.inTable = void 0;
}
function Un(e) {
	this.enter({
		type: "tableRow",
		children: []
	}, e);
}
function Wn(e) {
	this.exit(e);
}
function Gn(e) {
	this.enter({
		type: "tableCell",
		children: []
	}, e);
}
function Kn(e) {
	let t = this.resume();
	this.data.inTable && (t = t.replace(/\\([\\|])/g, qn));
	let n = this.stack[this.stack.length - 1];
	n.type, n.value = t, this.exit(e);
}
function qn(e, t) {
	return t === "|" ? t : e;
}
function Jn(t) {
	let n = t || {}, r = n.tableCellPadding, i = n.tablePipeAlign, a = n.stringLength, o = r ? " " : "|";
	return {
		unsafe: [
			{
				character: "\r",
				inConstruct: "tableCell"
			},
			{
				character: "\n",
				inConstruct: "tableCell"
			},
			{
				atBreak: !0,
				character: "|",
				after: "[	 :-]"
			},
			{
				character: "|",
				inConstruct: "tableCell"
			},
			{
				atBreak: !0,
				character: ":",
				after: "-"
			},
			{
				atBreak: !0,
				character: "-",
				after: "[:|-]"
			}
		],
		handlers: {
			inlineCode: p,
			table: s,
			tableCell: l,
			tableRow: c
		}
	};
	function s(e, t, n, r) {
		return u(d(e, n, r), e.align);
	}
	function c(e, t, n, r) {
		let i = u([f(e, n, r)]);
		return i.slice(0, i.indexOf("\n"));
	}
	function l(e, t, n, r) {
		let i = n.enter("tableCell"), a = n.enter("phrasing"), s = n.containerPhrasing(e, {
			...r,
			before: o,
			after: o
		});
		return a(), i(), s;
	}
	function u(e, t) {
		return Ln(e, {
			align: t,
			alignDelimiters: i,
			padding: r,
			stringLength: a
		});
	}
	function d(e, t, n) {
		let r = e.children, i = -1, a = [], o = t.enter("table");
		for (; ++i < r.length;) a[i] = f(r[i], t, n);
		return o(), a;
	}
	function f(e, t, n) {
		let r = e.children, i = -1, a = [], o = t.enter("tableRow");
		for (; ++i < r.length;) a[i] = l(r[i], e, t, n);
		return o(), a;
	}
	function p(t, n, r) {
		let i = e.inlineCode(t, n, r);
		return r.stack.includes("tableCell") && (i = i.replace(/\|/g, "\\$&")), i;
	}
}
// node_modules/.pnpm/mdast-util-gfm-task-list-item@2.0.0/node_modules/mdast-util-gfm-task-list-item/lib/index.js
function Yn() {
	return { exit: {
		taskListCheckValueChecked: Zn,
		taskListCheckValueUnchecked: Zn,
		paragraph: Qn
	} };
}
function Xn() {
	return {
		unsafe: [{
			atBreak: !0,
			character: "-",
			after: "[:|-]"
		}],
		handlers: { listItem: $n }
	};
}
function Zn(e) {
	let t = this.stack[this.stack.length - 2];
	t.type, t.checked = e.type === "taskListCheckValueChecked";
}
function Qn(e) {
	let t = this.stack[this.stack.length - 2];
	if (t && t.type === "listItem" && typeof t.checked == "boolean") {
		let e = this.stack[this.stack.length - 1];
		e.type;
		let n = e.children[0];
		if (n && n.type === "text") {
			let r = t.children, i = -1, a;
			for (; ++i < r.length;) {
				let e = r[i];
				if (e.type === "paragraph") {
					a = e;
					break;
				}
			}
			a === e && (n.value = n.value.slice(1), n.value.length === 0 ? e.children.shift() : e.position && n.position && typeof n.position.start.offset == "number" && (n.position.start.column++, n.position.start.offset++, e.position.start = Object.assign({}, n.position.start)));
		}
	}
	this.exit(e);
}
function $n(t, n, r, i) {
	let a = t.children[0], o = typeof t.checked == "boolean" && a && a.type === "paragraph", s = "[" + (t.checked ? "x" : " ") + "] ", c = r.createTracker(i);
	o && c.move(s);
	let l = e.listItem(t, n, r, {
		...i,
		...c.current()
	});
	return o && (l = l.replace(/^(?:[*+-]|\d+\.)([\r\n]| {1,3})/, u)), l;
	function u(e) {
		return e + s;
	}
}
// node_modules/.pnpm/mdast-util-gfm@3.1.0/node_modules/mdast-util-gfm/lib/index.js
function er() {
	return [
		$t(),
		wn(),
		kn(),
		Bn(),
		Yn()
	];
}
function tr(e) {
	return { extensions: [
		en(),
		Tn(e),
		An(),
		Jn(e),
		Xn()
	] };
}
// node_modules/.pnpm/micromark-extension-gfm-autolink-literal@2.1.0/node_modules/micromark-extension-gfm-autolink-literal/lib/syntax.js
var nr = {
	tokenize: pr,
	partial: !0
}, rr = {
	tokenize: mr,
	partial: !0
}, ir = {
	tokenize: hr,
	partial: !0
}, ar = {
	tokenize: gr,
	partial: !0
}, or = {
	tokenize: _r,
	partial: !0
}, sr = {
	name: "wwwAutolink",
	tokenize: dr,
	previous: vr
}, cr = {
	name: "protocolAutolink",
	tokenize: fr,
	previous: yr
}, O = {
	name: "emailAutolink",
	tokenize: ur,
	previous: br
}, k = {};
function lr() {
	return { text: k };
}
for (var A = 48; A < 123;) k[A] = O, A++, A === 58 ? A = 65 : A === 91 && (A = 97);
k[43] = O, k[45] = O, k[46] = O, k[95] = O, k[72] = [O, cr], k[104] = [O, cr], k[87] = [O, sr], k[119] = [O, sr];
function ur(e, t, n) {
	let r = this, i, a;
	return s;
	function s(t) {
		return !xr(t) || !br.call(r, r.previous) || Sr(r.events) ? n(t) : (e.enter("literalAutolink"), e.enter("literalAutolinkEmail"), c(t));
	}
	function c(t) {
		return xr(t) ? (e.consume(t), c) : t === 64 ? (e.consume(t), l) : n(t);
	}
	function l(t) {
		return t === 46 ? e.check(or, d, u)(t) : t === 45 || t === 95 || o(t) ? (a = !0, e.consume(t), l) : d(t);
	}
	function u(t) {
		return e.consume(t), i = !0, l;
	}
	function d(o) {
		return a && i && _(r.previous) ? (e.exit("literalAutolinkEmail"), e.exit("literalAutolink"), t(o)) : n(o);
	}
}
function dr(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return t !== 87 && t !== 119 || !vr.call(r, r.previous) || Sr(r.events) ? n(t) : (e.enter("literalAutolink"), e.enter("literalAutolinkWww"), e.check(nr, e.attempt(rr, e.attempt(ir, a), n), n)(t));
	}
	function a(n) {
		return e.exit("literalAutolinkWww"), e.exit("literalAutolink"), t(n);
	}
}
function fr(e, t, n) {
	let r = this, i = "", o = !1;
	return s;
	function s(t) {
		return (t === 72 || t === 104) && yr.call(r, r.previous) && !Sr(r.events) ? (e.enter("literalAutolink"), e.enter("literalAutolinkHttp"), i += String.fromCodePoint(t), e.consume(t), l) : n(t);
	}
	function l(t) {
		if (_(t) && i.length < 5) return i += String.fromCodePoint(t), e.consume(t), l;
		if (t === 58) {
			let n = i.toLowerCase();
			if (n === "http" || n === "https") return e.consume(t), u;
		}
		return n(t);
	}
	function u(t) {
		return t === 47 ? (e.consume(t), o ? d : (o = !0, u)) : n(t);
	}
	function d(t) {
		return t === null || te(t) || a(t) || c(t) || ue(t) ? n(t) : e.attempt(rr, e.attempt(ir, f), n)(t);
	}
	function f(n) {
		return e.exit("literalAutolinkHttp"), e.exit("literalAutolink"), t(n);
	}
}
function pr(e, t, n) {
	let r = 0;
	return i;
	function i(t) {
		return (t === 87 || t === 119) && r < 3 ? (r++, e.consume(t), i) : t === 46 && r === 3 ? (e.consume(t), a) : n(t);
	}
	function a(e) {
		return e === null ? n(e) : t(e);
	}
}
function mr(e, t, n) {
	let r, i, o;
	return s;
	function s(t) {
		return t === 46 || t === 95 ? e.check(ar, u, l)(t) : t === null || a(t) || c(t) || t !== 45 && ue(t) ? u(t) : (o = !0, e.consume(t), s);
	}
	function l(t) {
		return t === 95 ? r = !0 : (i = r, r = void 0), e.consume(t), s;
	}
	function u(e) {
		return i || r || !o ? n(e) : t(e);
	}
}
function hr(e, t) {
	let n = 0, r = 0;
	return i;
	function i(s) {
		return s === 40 ? (n++, e.consume(s), i) : s === 41 && r < n ? o(s) : s === 33 || s === 34 || s === 38 || s === 39 || s === 41 || s === 42 || s === 44 || s === 46 || s === 58 || s === 59 || s === 60 || s === 63 || s === 93 || s === 95 || s === 126 ? e.check(ar, t, o)(s) : s === null || a(s) || c(s) ? t(s) : (e.consume(s), i);
	}
	function o(t) {
		return t === 41 && r++, e.consume(t), i;
	}
}
function gr(e, t, n) {
	return r;
	function r(s) {
		return s === 33 || s === 34 || s === 39 || s === 41 || s === 42 || s === 44 || s === 46 || s === 58 || s === 59 || s === 63 || s === 95 || s === 126 ? (e.consume(s), r) : s === 38 ? (e.consume(s), o) : s === 93 ? (e.consume(s), i) : s === 60 || s === null || a(s) || c(s) ? t(s) : n(s);
	}
	function i(e) {
		return e === null || e === 40 || e === 91 || a(e) || c(e) ? t(e) : r(e);
	}
	function o(e) {
		return _(e) ? s(e) : n(e);
	}
	function s(t) {
		return t === 59 ? (e.consume(t), r) : _(t) ? (e.consume(t), s) : n(t);
	}
}
function _r(e, t, n) {
	return r;
	function r(t) {
		return e.consume(t), i;
	}
	function i(e) {
		return o(e) ? n(e) : t(e);
	}
}
function vr(e) {
	return e === null || e === 40 || e === 42 || e === 95 || e === 91 || e === 93 || e === 126 || a(e);
}
function yr(e) {
	return !_(e);
}
function br(e) {
	return !(e === 47 || xr(e));
}
function xr(e) {
	return e === 43 || e === 45 || e === 46 || e === 95 || o(e);
}
function Sr(e) {
	let t = e.length, n = !1;
	for (; t--;) {
		let r = e[t][1];
		if ((r.type === "labelLink" || r.type === "labelImage") && !r._balanced) {
			n = !0;
			break;
		}
		if (r._gfmAutolinkLiteralWalkedInto) {
			n = !1;
			break;
		}
	}
	return e.length > 0 && !n && (e[e.length - 1][1]._gfmAutolinkLiteralWalkedInto = !0), n;
}
// node_modules/.pnpm/micromark-extension-gfm-footnote@2.1.0/node_modules/micromark-extension-gfm-footnote/lib/syntax.js
var Cr = {
	tokenize: jr,
	partial: !0
};
function wr() {
	return {
		document: { 91: {
			name: "gfmFootnoteDefinition",
			tokenize: Or,
			continuation: { tokenize: kr },
			exit: Ar
		} },
		text: {
			91: {
				name: "gfmFootnoteCall",
				tokenize: Dr
			},
			93: {
				name: "gfmPotentialFootnoteCall",
				add: "after",
				tokenize: Tr,
				resolveTo: Er
			}
		}
	};
}
function Tr(e, t, n) {
	let r = this, i = r.events.length, a = r.parser.gfmFootnotes || (r.parser.gfmFootnotes = []), o;
	for (; i--;) {
		let e = r.events[i][1];
		if (e.type === "labelImage") {
			o = e;
			break;
		}
		if (e.type === "gfmFootnoteCall" || e.type === "labelLink" || e.type === "label" || e.type === "image" || e.type === "link") break;
	}
	return s;
	function s(i) {
		if (!o || !o._balanced) return n(i);
		let s = v(r.sliceSerialize({
			start: o.end,
			end: r.now()
		}));
		return s.codePointAt(0) !== 94 || !a.includes(s.slice(1)) ? n(i) : (e.enter("gfmFootnoteCallLabelMarker"), e.consume(i), e.exit("gfmFootnoteCallLabelMarker"), t(i));
	}
}
function Er(e, t) {
	let n = e.length;
	for (; n--;) if (e[n][1].type === "labelImage" && e[n][0] === "enter") {
		e[n][1];
		break;
	}
	e[n + 1][1].type = "data", e[n + 3][1].type = "gfmFootnoteCallLabelMarker";
	let r = {
		type: "gfmFootnoteCall",
		start: Object.assign({}, e[n + 3][1].start),
		end: Object.assign({}, e[e.length - 1][1].end)
	}, i = {
		type: "gfmFootnoteCallMarker",
		start: Object.assign({}, e[n + 3][1].end),
		end: Object.assign({}, e[n + 3][1].end)
	};
	i.end.column++, i.end.offset++, i.end._bufferIndex++;
	let a = {
		type: "gfmFootnoteCallString",
		start: Object.assign({}, i.end),
		end: Object.assign({}, e[e.length - 1][1].start)
	}, o = {
		type: "chunkString",
		contentType: "string",
		start: Object.assign({}, a.start),
		end: Object.assign({}, a.end)
	}, s = [
		e[n + 1],
		e[n + 2],
		[
			"enter",
			r,
			t
		],
		e[n + 3],
		e[n + 4],
		[
			"enter",
			i,
			t
		],
		[
			"exit",
			i,
			t
		],
		[
			"enter",
			a,
			t
		],
		[
			"enter",
			o,
			t
		],
		[
			"exit",
			o,
			t
		],
		[
			"exit",
			a,
			t
		],
		e[e.length - 2],
		e[e.length - 1],
		[
			"exit",
			r,
			t
		]
	];
	return e.splice(n, e.length - n + 1, ...s), e;
}
function Dr(e, t, n) {
	let r = this, i = r.parser.gfmFootnotes || (r.parser.gfmFootnotes = []), o = 0, s;
	return c;
	function c(t) {
		return e.enter("gfmFootnoteCall"), e.enter("gfmFootnoteCallLabelMarker"), e.consume(t), e.exit("gfmFootnoteCallLabelMarker"), l;
	}
	function l(t) {
		return t === 94 ? (e.enter("gfmFootnoteCallMarker"), e.consume(t), e.exit("gfmFootnoteCallMarker"), e.enter("gfmFootnoteCallString"), e.enter("chunkString").contentType = "string", u) : n(t);
	}
	function u(c) {
		if (o > 999 || c === 93 && !s || c === null || c === 91 || a(c)) return n(c);
		if (c === 93) {
			e.exit("chunkString");
			let a = e.exit("gfmFootnoteCallString");
			return i.includes(v(r.sliceSerialize(a))) ? (e.enter("gfmFootnoteCallLabelMarker"), e.consume(c), e.exit("gfmFootnoteCallLabelMarker"), e.exit("gfmFootnoteCall"), t) : n(c);
		}
		return a(c) || (s = !0), o++, e.consume(c), c === 92 ? d : u;
	}
	function d(t) {
		return t === 91 || t === 92 || t === 93 ? (e.consume(t), o++, u) : u(t);
	}
}
function Or(e, t, r) {
	let i = this, o = i.parser.gfmFootnotes || (i.parser.gfmFootnotes = []), s, c = 0, l;
	return u;
	function u(t) {
		return e.enter("gfmFootnoteDefinition")._container = !0, e.enter("gfmFootnoteDefinitionLabel"), e.enter("gfmFootnoteDefinitionLabelMarker"), e.consume(t), e.exit("gfmFootnoteDefinitionLabelMarker"), d;
	}
	function d(t) {
		return t === 94 ? (e.enter("gfmFootnoteDefinitionMarker"), e.consume(t), e.exit("gfmFootnoteDefinitionMarker"), e.enter("gfmFootnoteDefinitionLabelString"), e.enter("chunkString").contentType = "string", f) : r(t);
	}
	function f(t) {
		if (c > 999 || t === 93 && !l || t === null || t === 91 || a(t)) return r(t);
		if (t === 93) {
			e.exit("chunkString");
			let n = e.exit("gfmFootnoteDefinitionLabelString");
			return s = v(i.sliceSerialize(n)), e.enter("gfmFootnoteDefinitionLabelMarker"), e.consume(t), e.exit("gfmFootnoteDefinitionLabelMarker"), e.exit("gfmFootnoteDefinitionLabel"), m;
		}
		return a(t) || (l = !0), c++, e.consume(t), t === 92 ? p : f;
	}
	function p(t) {
		return t === 91 || t === 92 || t === 93 ? (e.consume(t), c++, f) : f(t);
	}
	function m(t) {
		return t === 58 ? (e.enter("definitionMarker"), e.consume(t), e.exit("definitionMarker"), o.includes(s) || o.push(s), n(e, h, "gfmFootnoteDefinitionWhitespace")) : r(t);
	}
	function h(e) {
		return t(e);
	}
}
function kr(e, t, n) {
	return e.check(d, t, e.attempt(Cr, t, n));
}
function Ar(e) {
	e.exit("gfmFootnoteDefinition");
}
function jr(e, t, r) {
	let i = this;
	return n(e, a, "gfmFootnoteDefinitionIndent", 5);
	function a(e) {
		let n = i.events[i.events.length - 1];
		return n && n[1].type === "gfmFootnoteDefinitionIndent" && n[2].sliceSerialize(n[1], !0).length === 4 ? t(e) : r(e);
	}
}
// node_modules/.pnpm/micromark-extension-gfm-strikethrough@2.1.0/node_modules/micromark-extension-gfm-strikethrough/lib/syntax.js
function Mr(e) {
	let t = (e || {}).singleTilde, n = {
		name: "strikethrough",
		tokenize: i,
		resolveAll: r
	};
	return t ??= !0, {
		text: { 126: n },
		insideSpan: { null: [n] },
		attentionMarkers: { null: [126] }
	};
	function r(e, t) {
		let n = -1;
		for (; ++n < e.length;) if (e[n][0] === "enter" && e[n][1].type === "strikethroughSequenceTemporary" && e[n][1]._close) {
			let r = n;
			for (; r--;) if (e[r][0] === "exit" && e[r][1].type === "strikethroughSequenceTemporary" && e[r][1]._open && e[n][1].end.offset - e[n][1].start.offset === e[r][1].end.offset - e[r][1].start.offset) {
				e[n][1].type = "strikethroughSequence", e[r][1].type = "strikethroughSequence";
				let i = {
					type: "strikethrough",
					start: Object.assign({}, e[r][1].start),
					end: Object.assign({}, e[n][1].end)
				}, a = {
					type: "strikethroughText",
					start: Object.assign({}, e[r][1].end),
					end: Object.assign({}, e[n][1].start)
				}, o = [
					[
						"enter",
						i,
						t
					],
					[
						"enter",
						e[r][1],
						t
					],
					[
						"exit",
						e[r][1],
						t
					],
					[
						"enter",
						a,
						t
					]
				], s = t.parser.constructs.insideSpan.null;
				s && y(o, o.length, 0, ee(s, e.slice(r + 1, n), t)), y(o, o.length, 0, [
					[
						"exit",
						a,
						t
					],
					[
						"enter",
						e[n][1],
						t
					],
					[
						"exit",
						e[n][1],
						t
					],
					[
						"exit",
						i,
						t
					]
				]), y(e, r - 1, n - r + 3, o), n = r + o.length - 2;
				break;
			}
		}
		for (n = -1; ++n < e.length;) e[n][1].type === "strikethroughSequenceTemporary" && (e[n][1].type = "data");
		return e;
	}
	function i(e, n, r) {
		let i = this.previous, a = this.events, o = 0;
		return s;
		function s(t) {
			return i === 126 && a[a.length - 1][1].type !== "characterEscape" ? r(t) : (e.enter("strikethroughSequenceTemporary"), c(t));
		}
		function c(a) {
			let s = me(i);
			if (a === 126) return o > 1 ? r(a) : (e.consume(a), o++, c);
			if (o < 2 && !t) return r(a);
			let l = e.exit("strikethroughSequenceTemporary"), u = me(a);
			return l._open = !u || u === 2 && !!s, l._close = !s || s === 2 && !!u, n(a);
		}
	}
}
// node_modules/.pnpm/micromark-extension-gfm-table@2.1.2/node_modules/micromark-extension-gfm-table/lib/edit-map.js
var Nr = class {
	constructor() {
		this.map = [], this.index = /* @__PURE__ */ new Map();
	}
	add(e, t, n) {
		Pr(this, e, t, n);
	}
	consume(e) {
		/* c8 ignore next 3 -- `resolve` is never called without tables, so without edits. */
		if (this.map.sort(function(e, t) {
			return e[0] - t[0];
		}), this.map.length === 0) return;
		let t = this.map.length, n = [];
		for (; t > 0;) --t, n.push(e.slice(this.map[t][0] + this.map[t][1]), this.map[t][2]), e.length = this.map[t][0];
		n.push(e.slice()), e.length = 0;
		let r = n.pop();
		for (; r;) {
			for (let t of r) e.push(t);
			r = n.pop();
		}
		this.map.length = 0, this.index.clear();
	}
};
function Pr(e, t, n, r) {
	/* c8 ignore next 3 -- `resolve` is never called without tables, so without edits. */
	if (n === 0 && r.length === 0) return;
	let i = e.index.get(t);
	if (i) {
		i[1] += n, i[2].push(...r);
		return;
	}
	let a = [
		t,
		n,
		r
	];
	e.map.push(a), e.index.set(t, a);
}
// node_modules/.pnpm/micromark-extension-gfm-table@2.1.2/node_modules/micromark-extension-gfm-table/lib/infer.js
function Fr(e, t) {
	let n = !1, r = [];
	for (; t < e.length;) {
		let i = e[t];
		if (n) {
			if (i[0] === "enter") i[1].type === "tableContent" && r.push(e[t + 1][1].type === "tableDelimiterMarker" ? "left" : "none");
			else if (i[1].type === "tableContent") {
				if (e[t - 1][1].type === "tableDelimiterMarker") {
					let e = r.length - 1;
					r[e] = r[e] === "left" ? "center" : "right";
				}
			} else if (i[1].type === "tableDelimiterRow") break;
		} else i[0] === "enter" && i[1].type === "tableDelimiterRow" && (n = !0);
		t += 1;
	}
	return r;
}
// node_modules/.pnpm/micromark-extension-gfm-table@2.1.2/node_modules/micromark-extension-gfm-table/lib/syntax.js
function Ir() {
	return { flow: { null: {
		name: "table",
		tokenize: Lr,
		resolveAll: Rr
	} } };
}
function Lr(e, t, r) {
	let i = this, o = 0, s = 0, c;
	return u;
	function u(e) {
		let t = i.events.length - 1;
		for (; t > -1;) {
			let { type: e } = i.events[t][1];
			if (e === "lineEnding" || e === "linePrefix") t--;
			else break;
		}
		let n = t > -1 ? i.events[t][1].type : null, a = n === "tableHead" || n === "tableRow" ? x : d;
		return a === x && i.parser.lazy[i.now().line] ? r(e) : a(e);
	}
	function d(t) {
		return e.enter("tableHead"), e.enter("tableRow"), f(t);
	}
	function f(e) {
		return e === 124 || (c = !0, s += 1), p(e);
	}
	function p(t) {
		return t === null ? r(t) : re(t) ? s > 1 ? (s = 0, i.interrupt = !0, e.exit("tableRow"), e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), ee) : r(t) : l(t) ? n(e, p, "whitespace")(t) : (s += 1, c && (c = !1, o += 1), t === 124 ? (e.enter("tableCellDivider"), e.consume(t), e.exit("tableCellDivider"), c = !0, p) : (e.enter("data"), m(t)));
	}
	function m(t) {
		return t === null || t === 124 || a(t) ? (e.exit("data"), p(t)) : (e.consume(t), t === 92 ? h : m);
	}
	function h(t) {
		return t === 92 || t === 124 ? (e.consume(t), m) : m(t);
	}
	function ee(t) {
		return i.interrupt = !1, i.parser.lazy[i.now().line] ? r(t) : (e.enter("tableDelimiterRow"), c = !1, l(t) ? n(e, g, "linePrefix", i.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4)(t) : g(t));
	}
	function g(t) {
		return t === 45 || t === 58 ? ne(t) : t === 124 ? (c = !0, e.enter("tableCellDivider"), e.consume(t), e.exit("tableCellDivider"), te) : b(t);
	}
	function te(t) {
		return l(t) ? n(e, ne, "whitespace")(t) : ne(t);
	}
	function ne(t) {
		return t === 58 ? (s += 1, c = !0, e.enter("tableDelimiterMarker"), e.consume(t), e.exit("tableDelimiterMarker"), _) : t === 45 ? (s += 1, _(t)) : t === null || re(t) ? y(t) : b(t);
	}
	function _(t) {
		return t === 45 ? (e.enter("tableDelimiterFiller"), ie(t)) : b(t);
	}
	function ie(t) {
		return t === 45 ? (e.consume(t), ie) : t === 58 ? (c = !0, e.exit("tableDelimiterFiller"), e.enter("tableDelimiterMarker"), e.consume(t), e.exit("tableDelimiterMarker"), v) : (e.exit("tableDelimiterFiller"), v(t));
	}
	function v(t) {
		return l(t) ? n(e, y, "whitespace")(t) : y(t);
	}
	function y(n) {
		return n === 124 ? g(n) : n === null || re(n) ? !c || o !== s ? b(n) : (e.exit("tableDelimiterRow"), e.exit("tableHead"), t(n)) : b(n);
	}
	function b(e) {
		return r(e);
	}
	function x(t) {
		return e.enter("tableRow"), S(t);
	}
	function S(r) {
		return r === 124 ? (e.enter("tableCellDivider"), e.consume(r), e.exit("tableCellDivider"), S) : r === null || re(r) ? (e.exit("tableRow"), t(r)) : l(r) ? n(e, S, "whitespace")(r) : (e.enter("data"), ae(r));
	}
	function ae(t) {
		return t === null || t === 124 || a(t) ? (e.exit("data"), S(t)) : (e.consume(t), t === 92 ? oe : ae);
	}
	function oe(t) {
		return t === 92 || t === 124 ? (e.consume(t), ae) : ae(t);
	}
}
function Rr(e, t) {
	let n = -1, r = !0, i = 0, a = [
		0,
		0,
		0,
		0
	], o = [
		0,
		0,
		0,
		0
	], s = !1, c = 0, l, u, d, f = new Nr();
	for (; ++n < e.length;) {
		let p = e[n], m = p[1];
		p[0] === "enter" ? m.type === "tableHead" ? (s = !1, c !== 0 && (Br(f, t, c, l, u), u = void 0, c = 0), l = {
			type: "table",
			start: Object.assign({}, m.start),
			end: Object.assign({}, m.end)
		}, f.add(n, 0, [[
			"enter",
			l,
			t
		]])) : m.type === "tableRow" || m.type === "tableDelimiterRow" ? (r = !0, d = void 0, a = [
			0,
			0,
			0,
			0
		], o = [
			0,
			n + 1,
			0,
			0
		], s && (s = !1, u = {
			type: "tableBody",
			start: Object.assign({}, m.start),
			end: Object.assign({}, m.end)
		}, f.add(n, 0, [[
			"enter",
			u,
			t
		]])), i = m.type === "tableDelimiterRow" ? 2 : u ? 3 : 1) : i && (m.type === "data" || m.type === "tableDelimiterMarker" || m.type === "tableDelimiterFiller") ? (r = !1, o[2] === 0 && (a[1] !== 0 && (o[0] = o[1], d = zr(f, t, a, i, void 0, d), a = [
			0,
			0,
			0,
			0
		]), o[2] = n)) : m.type === "tableCellDivider" && (r ? r = !1 : (a[1] !== 0 && (o[0] = o[1], d = zr(f, t, a, i, void 0, d)), a = o, o = [
			a[1],
			n,
			0,
			0
		])) : m.type === "tableHead" ? (s = !0, c = n) : m.type === "tableRow" || m.type === "tableDelimiterRow" ? (c = n, a[1] === 0 ? o[1] !== 0 && (d = zr(f, t, o, i, n, d)) : (o[0] = o[1], d = zr(f, t, a, i, n, d)), i = 0) : i && (m.type === "data" || m.type === "tableDelimiterMarker" || m.type === "tableDelimiterFiller") && (o[3] = n);
	}
	for (c !== 0 && Br(f, t, c, l, u), f.consume(t.events), n = -1; ++n < t.events.length;) {
		let e = t.events[n];
		e[0] === "enter" && e[1].type === "table" && (e[1]._align = Fr(t.events, n));
	}
	return e;
}
function zr(e, t, n, r, i, a) {
	let o = r === 1 ? "tableHeader" : r === 2 ? "tableDelimiter" : "tableData";
	n[0] !== 0 && (a.end = Object.assign({}, j(t.events, n[0])), e.add(n[0], 0, [[
		"exit",
		a,
		t
	]]));
	let s = j(t.events, n[1]);
	if (a = {
		type: o,
		start: Object.assign({}, s),
		end: Object.assign({}, s)
	}, e.add(n[1], 0, [[
		"enter",
		a,
		t
	]]), n[2] !== 0) {
		let i = j(t.events, n[2]), a = j(t.events, n[3]), o = {
			type: "tableContent",
			start: Object.assign({}, i),
			end: Object.assign({}, a)
		};
		if (e.add(n[2], 0, [[
			"enter",
			o,
			t
		]]), r !== 2) {
			let r = t.events[n[2]], i = t.events[n[3]];
			if (r[1].end = Object.assign({}, i[1].end), r[1].type = "chunkText", r[1].contentType = "text", n[3] > n[2] + 1) {
				let t = n[2] + 1, r = n[3] - n[2] - 1;
				e.add(t, r, []);
			}
		}
		e.add(n[3] + 1, 0, [[
			"exit",
			o,
			t
		]]);
	}
	return i !== void 0 && (a.end = Object.assign({}, j(t.events, i)), e.add(i, 0, [[
		"exit",
		a,
		t
	]]), a = void 0), a;
}
function Br(e, t, n, r, i) {
	let a = [], o = j(t.events, n);
	i && (i.end = Object.assign({}, o), a.push([
		"exit",
		i,
		t
	])), r.end = Object.assign({}, o), a.push([
		"exit",
		r,
		t
	]), e.add(n + 1, 0, a);
}
function j(e, t) {
	let n = e[t], r = n[0] === "enter" ? "start" : "end";
	return n[1][r];
}
// node_modules/.pnpm/micromark-extension-gfm-task-list-item@2.1.0/node_modules/micromark-extension-gfm-task-list-item/lib/syntax.js
var Vr = {
	name: "tasklistCheck",
	tokenize: Ur
};
function Hr() {
	return { text: { 91: Vr } };
}
function Ur(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return r.previous !== null || !r._gfmTasklistFirstContentOfListItem ? n(t) : (e.enter("taskListCheck"), e.enter("taskListCheckMarker"), e.consume(t), e.exit("taskListCheckMarker"), o);
	}
	function o(t) {
		return a(t) ? (e.enter("taskListCheckValueUnchecked"), e.consume(t), e.exit("taskListCheckValueUnchecked"), s) : t === 88 || t === 120 ? (e.enter("taskListCheckValueChecked"), e.consume(t), e.exit("taskListCheckValueChecked"), s) : n(t);
	}
	function s(t) {
		return t === 93 ? (e.enter("taskListCheckMarker"), e.consume(t), e.exit("taskListCheckMarker"), e.exit("taskListCheck"), c) : n(t);
	}
	function c(r) {
		return re(r) ? t(r) : l(r) ? e.check({ tokenize: Wr }, t, n)(r) : n(r);
	}
}
function Wr(e, t, r) {
	return n(e, i, "whitespace");
	function i(e) {
		return e === null ? r(e) : t(e);
	}
}
// node_modules/.pnpm/micromark-extension-gfm@3.0.0/node_modules/micromark-extension-gfm/index.js
function Gr(e) {
	return ie([
		lr(),
		wr(),
		Mr(e),
		Ir(),
		Hr()
	]);
}
// node_modules/.pnpm/remark-gfm@4.0.1/node_modules/remark-gfm/lib/index.js
var Kr = {};
function qr(e) {
	let t = this, n = e || Kr, r = t.data(), i = r.micromarkExtensions ||= [], a = r.fromMarkdownExtensions ||= [], o = r.toMarkdownExtensions ||= [];
	i.push(Gr(n)), a.push(er()), o.push(tr(n));
}
// node_modules/.pnpm/@milkdown+preset-gfm@7.23.0/node_modules/@milkdown/preset-gfm/lib/index.js
function M(e, t) {
	return Object.assign(e, { meta: {
		package: "@milkdown/preset-gfm",
		...t
	} }), e;
}
var Jr = ve("strike_through");
M(Jr, {
	displayName: "Attr<strikethrough>",
	group: "Strikethrough"
});
var N = ge("strike_through", (e) => ({
	parseDOM: [{ tag: "del" }, {
		style: "text-decoration",
		getAttrs: (e) => e === "line-through"
	}],
	toDOM: (t) => ["del", e.get(Jr.key)(t)],
	parseMarkdown: {
		match: (e) => e.type === "delete",
		runner: (e, t, n) => {
			e.openMark(n), e.next(t.children), e.closeMark(n);
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "strike_through",
		runner: (e, t) => {
			e.withMark(t, "delete");
		}
	}
}));
M(N.mark, {
	displayName: "MarkSchema<strikethrough>",
	group: "Strikethrough"
}), M(N.ctx, {
	displayName: "MarkSchemaCtx<strikethrough>",
	group: "Strikethrough"
});
var Yr = w("ToggleStrikeThrough", (e) => () => fe(N.type(e)));
M(Yr, {
	displayName: "Command<ToggleStrikethrough>",
	group: "Strikethrough"
});
var Xr = Ce((e) => i(/(?<![\w:/])(~{1,2})(.+?)\1(?!\w|\/)$/, N.type(e)));
M(Xr, {
	displayName: "InputRule<strikethrough>",
	group: "Strikethrough"
});
var Zr = Se("strikeThroughKeymap", { ToggleStrikethrough: {
	shortcuts: "Mod-Alt-x",
	command: (e) => {
		let t = e.get(oe);
		return () => t.call(Yr.key);
	}
} });
M(Zr.ctx, {
	displayName: "KeymapCtx<strikethrough>",
	group: "Strikethrough"
}), M(Zr.shortcuts, {
	displayName: "Keymap<strikethrough>",
	group: "Strikethrough"
});
function Qr(e, t) {
	let n = t.content.firstChild;
	if (t.childCount !== 1 || n?.type.name !== "paragraph") {
		e.next(t.content);
		return;
	}
	let r = n.lastChild, i = r?.type.name === "hardbreak" ? n.content.cut(0, n.content.size - r.nodeSize) : n.content;
	e.next(i);
}
var $r = nt({
	tableGroup: "block",
	cellContent: "paragraph",
	cellAttributes: { alignment: {
		default: "left",
		getFromDOM: (e) => e.style.textAlign || "left",
		setDOMAttr: (e, t) => {
			t.style = `text-align: ${e || "left"}`;
		}
	} }
}), P = C("table", () => ({
	...$r.table,
	content: "table_header_row table_row+",
	disableDropCursor: !0,
	parseMarkdown: {
		match: (e) => e.type === "table",
		runner: (e, t, n) => {
			let r = t.align, i = t.children.map((e, t) => ({
				...e,
				align: r,
				isHeader: t === 0
			}));
			e.openNode(n), e.next(i), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "table",
		runner: (e, t) => {
			let n = t.content.firstChild?.content;
			if (!n) return;
			let r = [];
			n.forEach((e) => {
				r.push(e.attrs.alignment);
			}), e.openNode("table", void 0, { align: r }), e.next(t.content), e.closeNode();
		}
	}
}));
M(P.node, {
	displayName: "NodeSchema<table>",
	group: "Table"
}), M(P.ctx, {
	displayName: "NodeSchemaCtx<table>",
	group: "Table"
});
var ei = C("table_header_row", () => ({
	...$r.table_row,
	disableDropCursor: !0,
	content: "(table_header)*",
	parseDOM: [{ tag: "tr[data-is-header]" }, {
		tag: "tr",
		getAttrs: (e) => e instanceof HTMLElement && e.querySelector("th") ? {} : !1
	}],
	toDOM() {
		return [
			"tr",
			{ "data-is-header": !0 },
			0
		];
	},
	parseMarkdown: {
		match: (e) => !!(e.type === "tableRow" && e.isHeader),
		runner: (e, t, n) => {
			let r = t.align, i = t.children.map((e, n) => ({
				...e,
				align: r[n],
				isHeader: t.isHeader
			}));
			e.openNode(n), e.next(i), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "table_header_row",
		runner: (e, t) => {
			t.content.size !== 0 && (e.openNode("tableRow", void 0, { isHeader: !0 }), e.next(t.content), e.closeNode());
		}
	}
}));
M(ei.node, {
	displayName: "NodeSchema<tableHeaderRow>",
	group: "Table"
}), M(ei.ctx, {
	displayName: "NodeSchemaCtx<tableHeaderRow>",
	group: "Table"
});
var F = C("table_row", () => ({
	...$r.table_row,
	disableDropCursor: !0,
	content: "(table_cell)*",
	parseMarkdown: {
		match: (e) => e.type === "tableRow",
		runner: (e, t, n) => {
			let r = t.align, i = t.children.map((e, t) => ({
				...e,
				align: r[t]
			}));
			e.openNode(n), e.next(i), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "table_row",
		runner: (e, t) => {
			t.content.size !== 0 && (e.openNode("tableRow"), e.next(t.content), e.closeNode());
		}
	}
}));
M(F.node, {
	displayName: "NodeSchema<tableRow>",
	group: "Table"
}), M(F.ctx, {
	displayName: "NodeSchemaCtx<tableRow>",
	group: "Table"
});
var ti = C("table_cell", () => ({
	...$r.table_cell,
	disableDropCursor: !0,
	parseMarkdown: {
		match: (e) => e.type === "tableCell" && !e.isHeader,
		runner: (e, t, n) => {
			let r = t.align;
			e.openNode(n, { alignment: r }).openNode(e.schema.nodes.paragraph).next(t.children).closeNode().closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "table_cell",
		runner: (e, t) => {
			e.openNode("tableCell"), Qr(e, t), e.closeNode();
		}
	}
}));
M(ti.node, {
	displayName: "NodeSchema<tableCell>",
	group: "Table"
}), M(ti.ctx, {
	displayName: "NodeSchemaCtx<tableCell>",
	group: "Table"
});
var I = C("table_header", () => ({
	...$r.table_header,
	disableDropCursor: !0,
	parseMarkdown: {
		match: (e) => e.type === "tableCell" && !!e.isHeader,
		runner: (e, t, n) => {
			let r = t.align;
			e.openNode(n, { alignment: r }), e.openNode(e.schema.nodes.paragraph), e.next(t.children), e.closeNode(), e.closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === "table_header",
		runner: (e, t) => {
			e.openNode("tableCell"), Qr(e, t), e.closeNode();
		}
	}
}));
M(I.node, {
	displayName: "NodeSchema<tableHeader>",
	group: "Table"
}), M(I.ctx, {
	displayName: "NodeSchemaCtx<tableHeader>",
	group: "Table"
});
function ni(e, t = 3, n = 3) {
	let r = Array(n).fill(0).map(() => ti.type(e).createAndFill()), i = Array(n).fill(0).map(() => I.type(e).createAndFill()), a = Array(t).fill(0).map((t, n) => n === 0 ? ei.type(e).create(null, i) : F.type(e).create(null, r));
	return P.type(e).create(null, a);
}
function ri(e) {
	return (n, r) => (i) => {
		r ??= i.selection.from;
		let a = i.doc.resolve(r), o = t((e) => e.type.name === "table")(a), s = o ? {
			node: o.node,
			from: o.start
		} : void 0, c = e === "row";
		if (s) {
			let e = Ye.get(s.node);
			if (n >= 0 && n < (c ? e.height : e.width)) {
				let t = e.positionAt(c ? n : e.height - 1, c ? e.width - 1 : n, s.node), r = i.doc.resolve(s.from + t), a = c ? $e.rowSelection : $e.colSelection, o = e.positionAt(c ? n : 0, c ? 0 : n, s.node), l = i.doc.resolve(s.from + o);
				return u(i.setSelection(a(r, l)));
			}
		}
		return i;
	};
}
var ii = ri("row"), ai = ri("col");
function oi(e, t, { map: n, tableStart: r, table: i }, a) {
	let o = Array(a).fill(0).reduce((e, t, n) => e + i.child(n).nodeSize, r), s = Array(n.width).fill(0).map((t, r) => {
		let a = i.nodeAt(n.map[r]);
		return ti.type(e).createAndFill({ alignment: a?.attrs.alignment });
	});
	return t.insert(o, F.type(e).create(null, s)), t;
}
function si(e) {
	let t = He(e.$from);
	if (!t) return;
	let n = Ye.get(t.node);
	return n.cellsInRect({
		left: 0,
		right: n.width,
		top: 0,
		bottom: n.height
	}).map((e) => {
		let n = t.node.nodeAt(e), r = e + t.start;
		return {
			pos: r,
			start: r + 1,
			node: n
		};
	});
}
function ci(e) {
	let t = si(e.selection);
	if (t && t[0]) {
		let n = e.doc.resolve(t[0].pos), r = t[t.length - 1];
		if (r) {
			let t = e.doc.resolve(r.pos);
			return u(e.setSelection(new $e(t, n)));
		}
	}
	return e;
}
var li = w("GoToPrevTableCell", () => () => Ue(-1));
M(li, {
	displayName: "Command<goToPrevTableCellCommand>",
	group: "Table"
});
var ui = w("GoToNextTableCell", () => () => Ue(1));
M(ui, {
	displayName: "Command<goToNextTableCellCommand>",
	group: "Table"
});
var di = w("ExitTable", (e) => () => (t, n) => {
	if (!Xe(t)) return !1;
	let { $head: r } = t.selection, i = ne(r, P.type(e));
	if (!i) return !1;
	let { to: a } = i, o = t.tr.replaceWith(a, a, ze.type(e).createAndFill());
	return o.setSelection(b.near(o.doc.resolve(a), 1)).scrollIntoView(), n?.(o), !0;
});
M(di, {
	displayName: "Command<breakTableCommand>",
	group: "Table"
});
var fi = w("InsertTable", (e) => ({ row: t, col: n } = {}) => (r, i) => {
	let { selection: a, tr: o } = r, { from: s } = a, c = ni(e, t, n), l = o.replaceSelectionWith(c), u = b.findFrom(l.doc.resolve(s), 1, !0);
	return u && l.setSelection(u), i?.(l), !0;
});
M(fi, {
	displayName: "Command<insertTableCommand>",
	group: "Table"
});
var pi = w("MoveRow", () => ({ from: e, to: t, pos: n } = {}) => Ge({
	from: e ?? 0,
	to: t ?? 0,
	pos: n
}));
M(pi, {
	displayName: "Command<moveRowCommand>",
	group: "Table"
});
var mi = w("MoveCol", () => ({ from: e, to: t, pos: n } = {}) => Je({
	from: e ?? 0,
	to: t ?? 0,
	pos: n
}));
M(mi, {
	displayName: "Command<moveColCommand>",
	group: "Table"
});
var hi = w("SelectRow", () => (e = { index: 0 }) => (t, n) => {
	let { tr: r } = t;
	return !!n?.(ii(e.index, e.pos)(r));
});
M(hi, {
	displayName: "Command<selectRowCommand>",
	group: "Table"
});
var gi = w("SelectCol", () => (e = { index: 0 }) => (t, n) => {
	let { tr: r } = t;
	return !!n?.(ai(e.index, e.pos)(r));
});
M(gi, {
	displayName: "Command<selectColCommand>",
	group: "Table"
});
var _i = w("SelectTable", () => () => (e, t) => {
	let { tr: n } = e;
	return !!t?.(ci(n));
});
M(_i, {
	displayName: "Command<selectTableCommand>",
	group: "Table"
});
var vi = w("DeleteSelectedCells", () => () => (e, t) => {
	let { selection: n } = e;
	if (!(n instanceof $e)) return !1;
	let r = n.isRowSelection(), i = n.isColSelection();
	return r && i ? et(e, t) : i ? Ve(e, t) : qe(e, t);
});
M(vi, {
	displayName: "Command<deleteSelectedCellsCommand>",
	group: "Table"
});
var yi = w("AddColBefore", () => () => Ke);
M(yi, {
	displayName: "Command<addColBeforeCommand>",
	group: "Table"
});
var bi = w("AddColAfter", () => () => Ze);
M(bi, {
	displayName: "Command<addColAfterCommand>",
	group: "Table"
});
var xi = w("AddRowBefore", (e) => () => (t, n) => {
	if (!Xe(t)) return !1;
	if (n) {
		let r = We(t);
		n(oi(e, t.tr, r, r.top));
	}
	return !0;
});
M(xi, {
	displayName: "Command<addRowBeforeCommand>",
	group: "Table"
});
var Si = w("AddRowAfter", (e) => () => (t, n) => {
	if (!Xe(t)) return !1;
	if (n) {
		let r = We(t);
		n(oi(e, t.tr, r, r.bottom));
	}
	return !0;
});
M(Si, {
	displayName: "Command<addRowAfterCommand>",
	group: "Table"
});
var Ci = w("SetAlign", () => (e = "left") => Be("alignment", e));
M(Ci, {
	displayName: "Command<setAlignCommand>",
	group: "Table"
});
var wi = Ce((e) => new s(/^\|(?<col>\d+)[xX](?<row>\d+)\|\s$/, (t, n, r, i) => {
	let a = t.doc.resolve(r);
	if (!a.node(-1).canReplaceWith(a.index(-1), a.indexAfter(-1), P.type(e))) return null;
	let o = ni(e, Math.max(Number(n.groups?.row ?? 0), 2), Number(n.groups?.col)), s = t.tr.replaceRangeWith(r, i, o);
	return s.setSelection(pe.create(s.doc, r + 3)).scrollIntoView();
}));
M(wi, {
	displayName: "InputRule<insertTableInputRule>",
	group: "Table"
});
var Ti = ye((e) => ({ run: (t, n, r) => {
	if (r) return t;
	function i(t) {
		let n = t.childCount, r = t.lastChild?.childCount ?? 0;
		if (n === 0 || r === 0) return ze.type(e).create();
		let i = t.firstChild;
		if (!(r > 0 && i && i.childCount === 0)) return t;
		if (n >= 3) {
			let r = t.child(1), a = [];
			for (let t = 0; t < r.childCount; t++) {
				let n = r.child(t);
				a.push(I.type(e).create(n.attrs, n.content, n.marks));
			}
			let o = i.type.create(i.attrs, a), s = [];
			for (let e = 2; e < n; e++) s.push(t.child(e));
			return t.type.create(t.attrs, [o, ...s]);
		}
		let a = Array(r).fill(0).map(() => I.type(e).createAndFill()), o = new h(g.from(a), 0, 0), s = i.replace(0, 0, o);
		return t.replace(0, i.nodeSize, new h(g.from(s), 0, 0));
	}
	function a(t) {
		let n = F.type(e), r = [], a = [], o = !1;
		function s() {
			if (a.length === 0) return;
			let t = ei.type(e).createAndFill(), n = P.type(e).create(null, [t, ...a]);
			r.push(i(n)), a = [];
		}
		return t.forEach((e) => {
			e.type === n ? (o = !0, a.push(e)) : (s(), r.push(e));
		}), s(), o ? g.from(r) : t;
	}
	function o(t) {
		let n = a(t), r = n !== t, s = [];
		return n.forEach((t) => {
			if (t.type === P.type(e)) {
				let e = i(t);
				e !== t && (r = !0), s.push(e);
			} else if (t.childCount > 0) {
				let e = o(t.content);
				e === t.content ? s.push(t) : (r = !0, s.push(t.copy(e)));
			} else s.push(t);
		}), r ? g.from(s) : t;
	}
	function s(t) {
		let n = [], r = [];
		t.forEach((e) => r.push(e));
		for (let t = 0; t < r.length; t++) {
			let i = r[t], a = r[t + 1];
			i.type === ze.type(e) && i.content.size === 0 && a && a.type === P.type(e) || n.push(i);
		}
		return n.length < r.length ? g.from(n) : t;
	}
	let c = o(t.content);
	return c = s(c), new h(g.from(c), t.openStart, t.openEnd);
} }));
M(Ti, {
	displayName: "PasteRule<table>",
	group: "Table"
});
var Ei = Se("tableKeymap", {
	NextCell: {
		priority: 100,
		shortcuts: ["Mod-]", "Tab"],
		command: (e) => {
			let t = e.get(oe);
			return () => t.call(ui.key);
		}
	},
	PrevCell: {
		shortcuts: ["Mod-[", "Shift-Tab"],
		command: (e) => {
			let t = e.get(oe);
			return () => t.call(li.key);
		}
	},
	ExitTable: {
		shortcuts: ["Mod-Enter", "Enter"],
		command: (e) => {
			let t = e.get(oe);
			return () => t.call(di.key);
		}
	}
});
M(Ei.ctx, {
	displayName: "KeymapCtx<table>",
	group: "Table"
}), M(Ei.shortcuts, {
	displayName: "Keymap<table>",
	group: "Table"
});
var Di = "footnote_definition", Oi = "footnoteDefinition", ki = C("footnote_definition", () => ({
	group: "block",
	content: "block+",
	defining: !0,
	attrs: { label: {
		default: "",
		validate: "string"
	} },
	parseDOM: [{
		tag: `dl[data-type="${Di}"]`,
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw x(e);
			return { label: e.dataset.label };
		},
		contentElement: "dd"
	}],
	toDOM: (e) => {
		let t = e.attrs.label;
		return [
			"dl",
			{
				"data-label": t,
				"data-type": Di
			},
			["dt", t],
			["dd", 0]
		];
	},
	parseMarkdown: {
		match: ({ type: e }) => e === Oi,
		runner: (e, t, n) => {
			e.openNode(n, { label: t.label }).next(t.children).closeNode();
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === Di,
		runner: (e, t) => {
			e.openNode(Oi, void 0, {
				label: t.attrs.label,
				identifier: t.attrs.label
			}).next(t.content).closeNode();
		}
	}
}));
M(ki.ctx, {
	displayName: "NodeSchemaCtx<footnodeDef>",
	group: "footnote"
}), M(ki.node, {
	displayName: "NodeSchema<footnodeDef>",
	group: "footnote"
});
var Ai = "footnote_reference", ji = C("footnote_reference", () => ({
	group: "inline",
	inline: !0,
	atom: !0,
	attrs: { label: {
		default: "",
		validate: "string"
	} },
	parseDOM: [{
		tag: `sup[data-type="${Ai}"]`,
		getAttrs: (e) => {
			if (!(e instanceof HTMLElement)) throw x(e);
			return { label: e.dataset.label };
		}
	}],
	toDOM: (e) => {
		let t = e.attrs.label;
		return [
			"sup",
			{
				"data-label": t,
				"data-type": Ai
			},
			t
		];
	},
	parseMarkdown: {
		match: ({ type: e }) => e === "footnoteReference",
		runner: (e, t, n) => {
			e.addNode(n, { label: t.label });
		}
	},
	toMarkdown: {
		match: (e) => e.type.name === Ai,
		runner: (e, t) => {
			e.addNode("footnoteReference", void 0, void 0, {
				label: t.attrs.label,
				identifier: t.attrs.label
			});
		}
	}
}));
M(ji.ctx, {
	displayName: "NodeSchemaCtx<footnodeRef>",
	group: "footnote"
}), M(ji.node, {
	displayName: "NodeSchema<footnodeRef>",
	group: "footnote"
});
var Mi = Ie.extendSchema((e) => (t) => {
	let n = e(t);
	return {
		...n,
		attrs: {
			...n.attrs,
			checked: {
				default: null,
				validate: "boolean|null"
			}
		},
		parseDOM: [{
			tag: "li[data-item-type=\"task\"]",
			getAttrs: (e) => {
				if (!(e instanceof HTMLElement)) throw x(e);
				return {
					label: e.dataset.label,
					listType: e.dataset.listType,
					spread: e.dataset.spread === "true",
					checked: e.dataset.checked ? e.dataset.checked === "true" : null
				};
			}
		}, ...n?.parseDOM || []],
		toDOM: (e) => n.toDOM && e.attrs.checked == null ? n.toDOM(e) : [
			"li",
			{
				"data-item-type": "task",
				"data-label": e.attrs.label,
				"data-list-type": e.attrs.listType,
				"data-spread": e.attrs.spread,
				"data-checked": e.attrs.checked
			},
			0
		],
		parseMarkdown: {
			match: ({ type: e }) => e === "listItem",
			runner: (e, t, r) => {
				if (t.checked == null) {
					n.parseMarkdown.runner(e, t, r);
					return;
				}
				let i = t.label == null ? "•" : `${t.label}.`, a = t.checked == null ? null : !!t.checked, o = t.label == null ? "bullet" : "ordered", s = t.spread ?? !0;
				e.openNode(r, {
					label: i,
					listType: o,
					spread: s,
					checked: a
				}), e.next(t.children), e.closeNode();
			}
		},
		toMarkdown: {
			match: (e) => e.type.name === "list_item",
			runner: (e, t) => {
				if (t.attrs.checked == null) {
					n.toMarkdown.runner(e, t);
					return;
				}
				let r = t.attrs.label, i = t.attrs.listType, a = t.attrs.spread, o = t.attrs.checked;
				e.openNode("listItem", void 0, {
					label: r,
					listType: i,
					spread: a,
					checked: o
				}), e.next(t.content), e.closeNode();
			}
		}
	};
});
M(Mi.node, {
	displayName: "NodeSchema<taskListItem>",
	group: "ListItem"
}), M(Mi.ctx, {
	displayName: "NodeSchemaCtx<taskListItem>",
	group: "ListItem"
});
var Ni = Ce(() => new s(/^\[(?<checked>\s|x)\]\s$/, (e, t, n, r) => {
	let i = e.doc.resolve(n), a = 0, o = i.node(a);
	for (; o && o.type.name !== "list_item";) a--, o = i.node(a);
	if (!o || o.attrs.checked != null) return null;
	let s = t.groups?.checked === "x", c = i.before(a), l = e.tr;
	return l.deleteRange(n, r).setNodeMarkup(c, void 0, {
		...o.attrs,
		checked: s
	}), l;
}));
M(Ni, {
	displayName: "InputRule<wrapInTaskListInputRule>",
	group: "ListItem"
});
var Pi = [Zr, Ei].flat(), Fi = [wi, Ni], Ii = [Xr], Li = [Ti], Ri = we(() => Wt);
M(Ri, {
	displayName: "Prose<autoInsertSpanPlugin>",
	group: "Prose"
}), M(we(() => Qe({})), {
	displayName: "Prose<columnResizingPlugin>",
	group: "Prose"
});
var zi = we(() => tt({ allowTableNodeSelection: !0 }));
M(zi, {
	displayName: "Prose<tableEditingPlugin>",
	group: "Prose"
});
var Bi = _e("remarkGFM", () => qr);
M(Bi.plugin, {
	displayName: "Remark<remarkGFMPlugin>",
	group: "Remark"
}), M(Bi.options, {
	displayName: "RemarkConfig<remarkGFMPlugin>",
	group: "Remark"
});
var Vi = new de("MILKDOWN_KEEP_TABLE_ALIGN_PLUGIN");
function Hi(e, t) {
	let n = 0;
	return t.forEach((t, r, i) => {
		t === e && (n = i);
	}), n;
}
function Ui(e, t) {
	let n = e.content.findDiffStart(t.content);
	if (n == null) return null;
	let r = e.content.findDiffEnd(t.content);
	return r ? {
		from: n,
		to: Math.max(r.b, n)
	} : null;
}
var Wi = we(() => new ce({
	key: Vi,
	appendTransaction: (e, t, n) => {
		if (t.doc === n.doc) return;
		let r = Ui(t.doc, n.doc);
		if (!r) return;
		let i, a = (e, t) => {
			if (e.type.name !== "table_cell") return;
			let r = n.doc.resolve(t), a = r.node(r.depth), o = r.node(r.depth - 1).firstChild;
			if (!o) return;
			let s = Hi(e, a), c = o.maybeChild(s);
			if (!c) return;
			let l = c.attrs.alignment;
			l !== e.attrs.alignment && (i ||= n.tr, i.setNodeMarkup(t, void 0, {
				...e.attrs,
				alignment: l
			}));
		};
		return n.doc.nodesBetween(r.from, r.to, (e, t) => e.type.name !== "table" || (n.doc.nodesBetween(t, t + e.nodeSize, a), !1)), i;
	}
}));
M(Wi, {
	displayName: "Prose<keepTableAlignPlugin>",
	group: "Prose"
});
var Gi = [
	Wi,
	Ri,
	Bi,
	zi
].flat(), Ki = [
	[
		Mi,
		P,
		ei,
		F,
		I,
		ti,
		ki,
		ji,
		Jr,
		N
	].flat(),
	Fi,
	Li,
	Ii,
	Pi,
	[
		ui,
		li,
		di,
		fi,
		pi,
		mi,
		hi,
		gi,
		_i,
		vi,
		xi,
		Si,
		yi,
		bi,
		Ci,
		Yr
	],
	Gi
].flat(), qi = Object.defineProperty, Ji = Object.getOwnPropertySymbols, Yi = Object.prototype.hasOwnProperty, Xi = Object.prototype.propertyIsEnumerable, Zi = (e, t, n) => t in e ? qi(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, Qi = (e, t) => {
	for (var n in t ||= {}) Yi.call(t, n) && Zi(e, n, t[n]);
	if (Ji) for (var n of Ji(t)) Xi.call(t, n) && Zi(e, n, t[n]);
	return e;
};
function L(e, t) {
	return Object.assign(e, { meta: Qi({ package: "@milkdown/components" }, t) }), e;
}
var $i = Object.defineProperty, ea = Object.getOwnPropertySymbols, ta = Object.prototype.hasOwnProperty, na = Object.prototype.propertyIsEnumerable, ra = (e, t, n) => t in e ? $i(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, ia = (e, t) => {
	for (var n in t ||= {}) ta.call(t, n) && ra(e, n, t[n]);
	if (ea) for (var n of ea(t)) na.call(t, n) && ra(e, n, t[n]);
	return e;
}, R = xe(ia({}, { mode: "preview" }), "linkTooltipStateCtx");
L(R, {
	displayName: "State<link-tooltip>",
	group: "LinkTooltip"
});
var z = xe(ia({}, {
	addLink: () => {},
	editLink: () => {},
	removeLink: () => {}
}), "linkTooltipAPICtx");
L(R, {
	displayName: "API<link-tooltip>",
	group: "LinkTooltip"
});
var B = xe(ia({}, {
	linkIcon: "🔗",
	editButton: "✎",
	removeButton: "⌫",
	confirmButton: "Confirm ⏎",
	onCopyLink: () => {},
	inputPlaceholder: "Paste link..."
}), "linkTooltipConfigCtx");
L(R, {
	displayName: "Config<link-tooltip>",
	group: "LinkTooltip"
});
var aa = w("ToggleLink", (e) => () => (t) => {
	let { doc: n, selection: r } = t, i = Re.type(e);
	return n.rangeHasMark(r.from, r.to, i) ? (e.get(z.key).removeLink(r.from, r.to), !0) : (e.get(z.key).addLink(r.from, r.to), !0);
}), oa = Fe("LINK_PREVIEW");
L(oa[0], {
	displayName: "PreviewTooltipSpec<link-tooltip>",
	group: "LinkTooltip"
}), L(oa[1], {
	displayName: "PreviewTooltipPlugin<link-tooltip>",
	group: "LinkTooltip"
});
var sa = Fe("LINK_EDIT");
L(sa[0], {
	displayName: "EditTooltipSpec<link-tooltip>",
	group: "LinkTooltip"
}), L(sa[1], {
	displayName: "EditTooltipPlugin<link-tooltip>",
	group: "LinkTooltip"
});
function V({ icon: e, class: t, onClick: n }) {
	return /* @__PURE__ */ E("span", {
		class: Ae("milkdown-icon", t),
		onPointerdown: n,
		innerHTML: e ? Te.sanitize(e.trim()) : void 0
	});
}
V.props = {
	icon: {
		type: String,
		required: !1
	},
	class: {
		type: String,
		required: !1
	},
	onClick: {
		type: Function,
		required: !1
	}
};
var ca = he("abcdefghijklmnopqrstuvwxyz0123456789", 8);
function la(e) {
	return `${e}-${ca()}`;
}
var ua = Me({
	props: {
		config: {
			type: Object,
			required: !0
		},
		src: {
			type: Object,
			required: !0
		},
		onConfirm: {
			type: Function,
			required: !0
		},
		onCancel: {
			type: Function,
			required: !0
		}
	},
	setup({ config: e, src: t, onConfirm: n, onCancel: r }) {
		let i = T(t), a = la("milkdown-link-edit");
		Oe(t, (e) => {
			i.value = e;
		});
		let o = () => {
			n(i.value);
		}, s = (e) => {
			e.stopPropagation(), e.key === "Enter" && (e.preventDefault(), o()), e.key === "Escape" && (e.preventDefault(), r());
		};
		return () => /* @__PURE__ */ E("div", { class: "link-edit" }, /* @__PURE__ */ E("input", {
			id: a,
			class: "input-area",
			placeholder: e.value.inputPlaceholder,
			onKeydown: s,
			onInput: (e) => {
				i.value = e.target.value;
			},
			value: i.value
		}), i.value ? /* @__PURE__ */ E(V, {
			class: "button confirm",
			icon: e.value.confirmButton,
			onClick: o
		}) : null);
	}
}), da = Object.defineProperty, fa = Object.defineProperties, pa = Object.getOwnPropertyDescriptors, ma = Object.getOwnPropertySymbols, ha = Object.prototype.hasOwnProperty, ga = Object.prototype.propertyIsEnumerable, _a = (e) => {
	throw TypeError(e);
}, va = (e, t, n) => t in e ? da(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, ya = (e, t) => {
	for (var n in t ||= {}) ha.call(t, n) && va(e, n, t[n]);
	if (ma) for (var n of ma(t)) ga.call(t, n) && va(e, n, t[n]);
	return e;
}, ba = (e, t) => fa(e, pa(t)), xa = (e, t, n) => t.has(e) || _a("Cannot " + n), H = (e, t, n) => (xa(e, t, "read from private field"), n ? n.call(e) : t.get(e)), U = (e, t, n) => t.has(e) ? _a("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n), W = (e, t, n, r) => (xa(e, t, "write to private field"), t.set(e, n), n), G, K, q, Sa, Ca, wa, J, Ta, Ea, Da, Oa, ka, Aa = {
	from: -1,
	to: -1,
	mark: null
}, ja = class {
	constructor(e, t) {
		this.ctx = e, U(this, G), U(this, K), U(this, q, ya({}, Aa)), U(this, Sa), U(this, Ca), U(this, wa, T("")), U(this, J, () => {
			H(this, Da).call(this), H(this, K).hide(), this.ctx.update(R.key, (e) => ba(ya({}, e), { mode: "preview" })), W(this, q, ya({}, Aa));
		}), U(this, Ta, (e) => {
			let t = e.target;
			t && (H(this, G).contains(t) || H(this, J).call(this));
		}), U(this, Ea, () => {
			document.addEventListener("pointerdown", H(this, Ta), !0);
		}), U(this, Da, () => {
			document.removeEventListener("pointerdown", H(this, Ta), !0);
		}), U(this, Oa, (e) => {
			let t = this.ctx.get(se), { from: n, to: r, mark: i } = H(this, q), a = Re.type(this.ctx), o = Le(e);
			if (i && i.attrs.href === o) {
				H(this, J).call(this);
				return;
			}
			let s = t.state.tr;
			if (i && s.removeMark(n, r, i), n === r) {
				if (!o) {
					H(this, J).call(this);
					return;
				}
				let e = a.create({ href: o });
				s.insertText(o, n), s.addMark(n, n + o.length, e);
			} else s.addMark(n, r, a.create({ href: o }));
			t.dispatch(s), H(this, J).call(this);
		}), U(this, ka, (e, t, n) => {
			let r = this.ctx.get(B.key);
			H(this, Ca).value = r, H(this, wa).value = e, this.ctx.update(R.key, (e) => ba(ya({}, e), { mode: "edit" }));
			let i = this.ctx.get(se);
			i.dispatch(i.state.tr.setSelection(pe.create(i.state.doc, t, n))), H(this, K).show({ getBoundingClientRect: () => le(i, t, n) }, i), H(this, Ea).call(this), requestAnimationFrame(() => {
				var e;
				(e = H(this, G).querySelector("input")) == null || e.focus();
			});
		}), this.update = (e) => {
			let { state: t } = e, { selection: n } = t;
			if (!(n instanceof pe)) return;
			let { from: r, to: i } = n;
			(r !== H(this, q).from || i !== H(this, q).to) && H(this, J).call(this);
		}, this.destroy = () => {
			H(this, Da).call(this), H(this, Sa).unmount(), H(this, K).destroy(), H(this, G).remove();
		}, this.addLink = (e, t) => {
			W(this, q, {
				from: e,
				to: t,
				mark: null
			}), H(this, ka).call(this, "", e, t);
		}, this.editLink = (e, t, n) => {
			W(this, q, {
				from: t,
				to: n,
				mark: e
			}), H(this, ka).call(this, e.attrs.href, t, n);
		}, this.removeLink = (e, t) => {
			let n = this.ctx.get(se), r = n.state.tr;
			r.removeMark(e, t, Re.type(this.ctx)), n.dispatch(r), H(this, J).call(this);
		}, W(this, Ca, T(this.ctx.get(B.key)));
		let n = document.createElement("div");
		n.className = "milkdown-link-edit";
		let r = je(ua, {
			config: H(this, Ca),
			src: H(this, wa),
			onConfirm: H(this, Oa),
			onCancel: H(this, J)
		});
		r.mount(n), W(this, Sa, r), W(this, G, n), W(this, K, new Pe({
			content: n,
			debounce: 0,
			shouldShow: () => !1
		})), H(this, K).onHide = () => {
			requestAnimationFrame(() => {
				t.dom.focus({ preventScroll: !0 });
			});
		}, H(this, K).update(t);
	}
};
G = /* @__PURE__ */ new WeakMap(), K = /* @__PURE__ */ new WeakMap(), q = /* @__PURE__ */ new WeakMap(), Sa = /* @__PURE__ */ new WeakMap(), Ca = /* @__PURE__ */ new WeakMap(), wa = /* @__PURE__ */ new WeakMap(), J = /* @__PURE__ */ new WeakMap(), Ta = /* @__PURE__ */ new WeakMap(), Ea = /* @__PURE__ */ new WeakMap(), Da = /* @__PURE__ */ new WeakMap(), Oa = /* @__PURE__ */ new WeakMap(), ka = /* @__PURE__ */ new WeakMap();
var Ma = Object.defineProperty, Na = Object.defineProperties, Pa = Object.getOwnPropertyDescriptors, Fa = Object.getOwnPropertySymbols, Ia = Object.prototype.hasOwnProperty, La = Object.prototype.propertyIsEnumerable, Ra = (e, t, n) => t in e ? Ma(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, za = (e, t) => {
	for (var n in t ||= {}) Ia.call(t, n) && Ra(e, n, t[n]);
	if (Fa) for (var n of Fa(t)) La.call(t, n) && Ra(e, n, t[n]);
	return e;
}, Ba = (e, t) => Na(e, Pa(t));
function Va(e) {
	let t;
	e.update(z.key, (e) => Ba(za({}, e), {
		addLink: (e, n) => {
			t?.addLink(e, n);
		},
		editLink: (e, n, r) => {
			t?.editLink(e, n, r);
		},
		removeLink: (e, n) => {
			t?.removeLink(e, n);
		}
	})), e.set(sa.key, { view: (n) => (t = new ja(e, n), t) });
}
function Ha(e, t, n, r, i) {
	let a = {
		start: -1,
		end: -1
	};
	return n.nodesBetween(r, i, (n, r) => {
		if (a.start > -1) return !1;
		a.start === -1 && e.isInSet(n.marks) && t === n && (a = {
			start: r,
			end: r + Math.max(n.textContent.length, 1)
		});
	}), a;
}
function Ua(e, t, n) {
	let r = t.posAtCoords({
		left: n.clientX,
		top: n.clientY
	});
	if (!r) return;
	let { pos: i } = r, a = t.state.doc.nodeAt(i);
	if (!a) return;
	let o = a.marks.find((t) => t.type === Re.mark.type(e));
	if (o && oa.pluginKey()) return {
		show: !0,
		pos: i,
		node: a,
		mark: o
	};
}
var Wa = Me({
	props: {
		config: {
			type: Object,
			required: !0
		},
		src: {
			type: Object,
			required: !0
		},
		onEdit: {
			type: Object,
			required: !0
		},
		onRemove: {
			type: Object,
			required: !0
		}
	},
	setup({ config: e, src: t, onEdit: n, onRemove: r }) {
		let i = ke(() => Le(t.value)), a = (e) => {
			e.preventDefault(), e.stopPropagation(), n.value();
		}, o = (e) => {
			e.preventDefault(), e.stopPropagation(), r.value();
		}, s = (n) => {
			n.preventDefault();
			let r = t.value;
			navigator.clipboard && r && navigator.clipboard.writeText(r).then(() => {
				e.value.onCopyLink(r);
			}).catch((e) => console.error(e));
		};
		return () => /* @__PURE__ */ E("div", { class: "link-preview" }, /* @__PURE__ */ E(V, {
			class: "button link-icon",
			icon: e.value.linkIcon,
			onClick: s
		}), /* @__PURE__ */ E("a", {
			href: i.value,
			target: "_blank",
			rel: "noopener noreferrer",
			class: "link-display"
		}, t.value), /* @__PURE__ */ E(V, {
			class: "button link-edit-button",
			icon: e.value.editButton,
			onClick: a
		}), /* @__PURE__ */ E(V, {
			class: "button link-remove-button",
			icon: e.value.removeButton,
			onClick: o
		}));
	}
}), Ga = (e) => {
	throw TypeError(e);
}, Ka = (e, t, n) => t.has(e) || Ga("Cannot " + n), Y = (e, t, n) => (Ka(e, t, "read from private field"), n ? n.call(e) : t.get(e)), X = (e, t, n) => t.has(e) ? Ga("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n), Z = (e, t, n, r) => (Ka(e, t, "write to private field"), t.set(e, n), n), Q, $, qa, Ja, Ya, Xa, Za, Qa, $a, eo, to, no, ro, io, ao = class {
	constructor(e, t) {
		this.ctx = e, X(this, Q), X(this, $), X(this, qa), X(this, Ja), X(this, Ya, T("")), X(this, Xa, T(() => {})), X(this, Za, T(() => {})), X(this, Qa), X(this, $a), X(this, eo, !1), X(this, to, ({ mode: e }) => {
			e === "edit" && Y(this, io).call(this);
		}), X(this, no, () => {
			Z(this, eo, !0);
		}), X(this, ro, () => {
			Z(this, eo, !1);
		}), X(this, io, () => {
			Y(this, $).hide(), Y(this, $).element.removeEventListener("mouseenter", Y(this, no)), Y(this, $).element.removeEventListener("mouseleave", Y(this, ro));
		}), this.show = (e, t, n, r) => {
			Y(this, Ja).value = this.ctx.get(B.key), Y(this, Ya).value = e.attrs.href, Y(this, Xa).value = () => {
				this.ctx.get(z.key).editLink(e, t, n);
			}, Y(this, Za).value = () => {
				this.ctx.get(z.key).removeLink(t, n), Y(this, io).call(this);
			}, Y(this, $).show({ getBoundingClientRect: () => r }, Y(this, $a)), Y(this, $).element.addEventListener("mouseenter", Y(this, no)), Y(this, $).element.addEventListener("mouseleave", Y(this, ro));
		}, this.hide = () => {
			Y(this, eo) || Y(this, io).call(this);
		}, this.update = () => {}, this.destroy = () => {
			Y(this, Qa).unmount(), Y(this, qa).off(Y(this, to)), Y(this, $).destroy(), Y(this, Q).remove();
		}, Z(this, $a, t), Z(this, Ja, T(this.ctx.get(B.key))), Z(this, Qa, je(Wa, {
			config: Y(this, Ja),
			src: Y(this, Ya),
			onEdit: Y(this, Xa),
			onRemove: Y(this, Za)
		})), Z(this, Q, document.createElement("div")), Y(this, Q).className = "milkdown-link-preview", Y(this, Qa).mount(Y(this, Q)), Z(this, $, new Pe({
			debounce: 0,
			content: Y(this, Q),
			shouldShow: () => !1
		})), Y(this, $).update(t), Z(this, qa, e.use(R.key)), Y(this, qa).on(Y(this, to));
	}
};
Q = /* @__PURE__ */ new WeakMap(), $ = /* @__PURE__ */ new WeakMap(), qa = /* @__PURE__ */ new WeakMap(), Ja = /* @__PURE__ */ new WeakMap(), Ya = /* @__PURE__ */ new WeakMap(), Xa = /* @__PURE__ */ new WeakMap(), Za = /* @__PURE__ */ new WeakMap(), Qa = /* @__PURE__ */ new WeakMap(), $a = /* @__PURE__ */ new WeakMap(), eo = /* @__PURE__ */ new WeakMap(), to = /* @__PURE__ */ new WeakMap(), no = /* @__PURE__ */ new WeakMap(), ro = /* @__PURE__ */ new WeakMap(), io = /* @__PURE__ */ new WeakMap();
function oo(e) {
	let t, n = Ne((n, r) => {
		if (!t || !n.hasFocus() || e.get(R.key).mode === "edit") return;
		let i = Ua(e, n, r);
		if (i) {
			let e = n.state.doc.resolve(i.pos), r = Ha(i.mark, i.node, n.state.doc, e.before(), e.after()), a = r.start, o = r.end;
			t.show(i.mark, a, o, le(n, a, o));
		} else t.hide();
	}, 50);
	e.set(oa.key, {
		props: { handleDOMEvents: {
			mousemove: n,
			mouseleave: () => {
				setTimeout(() => {
					t?.hide();
				}, 50);
			}
		} },
		view: (n) => (t = new ao(e, n), t)
	});
}
function so(e) {
	oo(e), Va(e);
}
var co = [
	R,
	z,
	B,
	oa,
	sa,
	aa
].flat();
export { lt as C, Gt as S, Ci as _, bi as a, P as b, xi as c, Ki as d, fi as f, hi as g, gi as h, aa as i, ni as l, pi as m, B as n, yi as o, mi as p, co as r, Si as s, so as t, vi as u, Zr as v, yt as w, Yr as x, N as y };
