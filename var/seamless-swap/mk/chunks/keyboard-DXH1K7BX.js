import { at as e, ot as t, st as n } from "./lib-9QpRoHAM.js";
import { d as r, m as i, u as a } from "./lib-DaU_YkmU.js";
import { n as o, t as s } from "./dist-BmAqUYId.js";
import { d as c, g as l } from "./lib-Cs-8MFWq.js";
import { f as u } from "./dist-DPp-l4A4.js";
import { a as d, i as f, n as p } from "./runtime-DBQRQGPT.js";
import { i as m, n as h, o as g, r as _ } from "./host-L1lfHHpX.js";
import { i as v, n as ee, r as y, t as b } from "./layer-N-kUiX52.js";
// src/Resources/assets/src/dialect/file-card.ts
var x = /* @__PURE__ */ new WeakMap(), S = null, C = "400px";
function w(e, t) {
	typeof IntersectionObserver < "u" && (S ??= new IntersectionObserver((e) => {
		for (let t of e) {
			let e = x.get(t.target);
			t.isIntersecting && e !== void 0 && (t.target.src = e, x.delete(t.target), S?.unobserve(t.target));
		}
	}, { rootMargin: C }), x.set(e, t), S.observe(e));
}
function T(e) {
	let t = e.replace(/\uFEFF/g, "").replace(/\s+/g, " ").replace(/[\p{Cc}\p{Cf}]/gu, "").replace(/\[/g, "(").replace(/\]/g, ")").trim();
	return Array.from(t).slice(0, 200).join("").trim() || "file";
}
var E = {
	pdf: "pdf",
	png: "image",
	jpg: "image",
	jpeg: "image",
	gif: "image",
	webp: "image",
	avif: "image",
	svg: "image",
	bmp: "image",
	heic: "image",
	mp3: "audio",
	wav: "audio",
	ogg: "audio",
	m4a: "audio",
	flac: "audio",
	mp4: "video",
	mov: "video",
	webm: "video",
	mkv: "video",
	avi: "video",
	zip: "archive",
	rar: "archive",
	"7z": "archive",
	tar: "archive",
	gz: "archive",
	tgz: "archive",
	bz2: "archive",
	csv: "sheet",
	tsv: "sheet",
	xls: "sheet",
	xlsx: "sheet",
	ods: "sheet",
	numbers: "sheet",
	doc: "doc",
	docx: "doc",
	odt: "doc",
	rtf: "doc",
	pages: "doc",
	ppt: "slides",
	pptx: "slides",
	odp: "slides",
	key: "slides",
	txt: "text",
	md: "text",
	log: "text",
	json: "code",
	xml: "code",
	yaml: "code",
	yml: "code",
	html: "code",
	css: "code",
	js: "code",
	ts: "code",
	php: "code",
	sql: "code"
};
function D(e) {
	let t = /\.([a-z0-9]{1,8})$/i.exec(e.trim());
	return t ? t[1].toLowerCase() : "";
}
function O(e) {
	let t = e.toLowerCase();
	if (t === "") return null;
	if (t === "application/pdf") return "pdf";
	if (/zip|compressed|tar|gzip/.test(t)) return "archive";
	if (/spreadsheet|excel|csv/.test(t)) return "sheet";
	if (/presentation|powerpoint/.test(t)) return "slides";
	if (/word|opendocument\.text|rtf/.test(t)) return "doc";
	for (let e of [
		"image",
		"audio",
		"video",
		"text"
	]) if (t.startsWith(`${e}/`)) return e;
	return null;
}
function k(e) {
	let t = e.split(/[?#]/, 1)[0] ?? "";
	return D(t.slice(t.lastIndexOf("/") + 1));
}
function te(e, t = "", n = "") {
	let r = (e) => e.slice(0, 4).toUpperCase(), i = k(n);
	if (i) return {
		category: E[i] ?? "other",
		badge: r(i)
	};
	let a = D(e), o = O(t);
	return o ? {
		category: o,
		badge: a && E[a] === o ? r(a) : "FILE"
	} : {
		category: E[a] ?? "other",
		badge: a ? r(a) : "FILE"
	};
}
function A(e) {
	let t = String(e ?? "");
	if (!/^\d{1,15}$/.test(t)) return "";
	let n = Number(t);
	if (n < 1024) return `${n} B`;
	let r = [
		"KB",
		"MB",
		"GB",
		"TB"
	], i = -1;
	for (; n >= 1024 && i < r.length - 1;) n /= 1024, i++;
	return `${n < 10 ? Math.round(n * 10) / 10 : Math.round(n)} ${r[i]}`;
}
var j = [
	"link",
	"icon",
	"card",
	"preview"
], M = {
	default: 480,
	min: 160,
	max: 1200,
	presets: {
		s: 320,
		m: 480,
		l: 720
	}
}, N = (e) => j.includes(e.view ?? "") ? e.view : "card";
function P(e) {
	if (!/^\d{1,4}$/.test(e ?? "")) return M.default;
	let t = Number(e);
	return t >= M.min && t <= M.max ? t : M.default;
}
var ne = {
	pdf: "file-type-pdf",
	image: "photo",
	audio: "music",
	video: "movie",
	archive: "file-zip",
	sheet: "file-type-xls",
	csv: "file-type-csv",
	doc: "file-type-doc",
	slides: "file-type-ppt",
	text: "file-type-txt",
	code: "file-code",
	other: "file"
}, re = (e) => ne[e.badge === "CSV" ? "csv" : e.category], ie = {
	hostSchemes: {},
	previewSandbox: ""
};
function ae(e, t, n = ie) {
	let r = T(e), i = g(t.href ?? "", "file", n.hostSchemes), a = te(r, t.type ?? "", i), o = N(t), s = (e) => {
		let t = document.createElement(i ? "a" : "span");
		return t.className = e, t instanceof HTMLAnchorElement && (t.href = i, t.rel = "noopener noreferrer", t.target = "_blank"), t;
	}, c = () => {
		let e = document.createElement("span");
		return e.className = "nowo-milkdown-file__name", e.textContent = r, e;
	};
	if (o === "link" || o === "icon") {
		let e = document.createElement("p");
		e.className = `nowo-milkdown-file nowo-milkdown-file--${o}-view nowo-milkdown-file--${a.category}`, e.dataset.view = o;
		let t = s(o === "link" ? "nowo-milkdown-file__text" : "nowo-milkdown-file__inline");
		if (o === "icon") {
			let e = v(re(a), "nowo-milkdown-file__icon");
			e && t.append(e), t.append(c());
		} else t.textContent = r;
		return e.append(t), e;
	}
	let l = s("nowo-milkdown-file__link"), u = document.createElement("span");
	u.className = "nowo-milkdown-file__badge", u.setAttribute("aria-hidden", "true"), u.textContent = a.badge, l.append(u, c());
	let d = A(t.size);
	if (d) {
		let e = document.createElement("span");
		e.className = "nowo-milkdown-file__size", e.textContent = d, l.append(e);
	}
	let f = o === "preview" && i ? _(t.href ?? "", "preview", n.hostSchemes) : null, p = f && (n.allowPreview?.(t.href ?? "") ?? !0) ? f : null;
	if (p) {
		let e = document.createElement("figure");
		e.className = `nowo-milkdown-file nowo-milkdown-file--preview-view nowo-milkdown-file--${a.category}`, e.dataset.view = "preview";
		let o = document.createElement("div");
		o.className = "nowo-milkdown-file__head";
		let s = document.createElement("span");
		s.className = "nowo-milkdown-file__actions";
		let c = document.createElement("a");
		c.className = "nowo-milkdown-file__open", c.href = i, c.target = "_blank", c.rel = "noopener noreferrer", c.textContent = n.label?.("open") ?? "Open";
		let u = document.createElement("a");
		u.className = "nowo-milkdown-file__download", u.href = i, u.rel = "noopener noreferrer", u.setAttribute("download", ""), u.textContent = n.label?.("download") ?? "Download", s.append(c, u), o.append(l, s);
		let d = document.createElement("iframe");
		return d.className = "nowo-milkdown-file__frame", d.title = r, d.setAttribute("sandbox", n.previewSandbox), d.setAttribute("loading", "lazy"), d.setAttribute("referrerpolicy", "no-referrer"), d.height = String(P(t.height)), w(d, p), e.append(o, d), e;
	}
	let m = document.createElement("div");
	return m.className = `nowo-milkdown-file nowo-milkdown-file--${a.category}`, o !== "card" && (m.dataset.view = o), m.append(l), m;
}
// src/Resources/assets/src/dialect/image.ts
var F = [
	"left",
	"center",
	"right"
], I = [
	"25%",
	"50%",
	"75%",
	"100%"
];
function L(e) {
	let t = typeof e == "string" ? e : "";
	if (I.includes(t)) return t;
	let n = /^([1-9]\d{1,3})px$/.exec(t);
	return n && Number(n[1]) >= 16 && Number(n[1]) <= 2e3 ? t : "";
}
var R = (e) => F.includes(e) ? e : "", z = "(width|align)=(?:\"([^\"{}\\n]{1,20})\"|([^ \\t\\n\\v\\f\\r\"{}]{1,20}))", B = RegExp(`^\\{[ \\t]*${z}(?:[ \\t]+${z})?[ \\t]*\\}`);
function V(e) {
	let t = B.exec(e);
	if (!t) return null;
	let n = {
		width: "",
		align: "",
		length: t[0].length
	};
	for (let [e, r] of [[t[1], t[2] ?? t[3]], [t[4], t[5] ?? t[6]]]) if (e === "width") {
		if (n.width = L(r), !n.width) return null;
	} else if (e === "align" && (n.align = R(r), !n.align)) return null;
	return n;
}
function H(e) {
	let t = [], n = L(e.width), r = R(e.align);
	return n && t.push(`width=${n}`), r && t.push(`align=${r}`), t.length ? `{${t.join(" ")}}` : "";
}
function U(e) {
	let t = e.children;
	if (t) {
		for (let e = t.length - 2; e >= 0; e--) {
			let n = t[e], r = t[e + 1];
			if (n.type !== "image" || r.type !== "text") continue;
			let i = V(r.value ?? "");
			if (!i) continue;
			n.data = {
				...n.data ?? {},
				nowoImage: {
					width: i.width,
					align: i.align
				}
			};
			let a = (r.value ?? "").slice(i.length);
			a === "" ? t.splice(e + 1, 1) : r.value = a;
		}
		for (let e of t) U(e);
	}
}
// src/Resources/assets/src/nodes/image.ts
var W = /^data:image\/(?:png|gif|jpe?g|webp)[;,]/i;
function G(e, t) {
	return typeof e == "string" && W.test(e) ? e : g(e, "image", t);
}
var K = c.extendSchema((e) => (t) => {
	let n = e(t);
	return {
		...n,
		attrs: {
			...n.attrs,
			width: {
				default: "",
				validate: "string"
			},
			align: {
				default: "",
				validate: "string"
			}
		},
		toDOM: (e) => {
			let [t, r] = n.toDOM(e), { width: i, align: a, ...o } = r;
			return [t, o];
		},
		parseMarkdown: {
			match: n.parseMarkdown.match,
			runner: (e, t, n) => {
				let r = t.data ?? {};
				e.addNode(n, {
					src: t.url,
					alt: t.alt ?? "",
					title: t.title ?? "",
					width: L(r.nowoImage?.width),
					align: R(r.nowoImage?.align)
				});
			}
		},
		toMarkdown: {
			match: n.toMarkdown.match,
			runner: (e, t) => {
				e.addNode("image", void 0, void 0, {
					title: t.attrs.title,
					url: t.attrs.src,
					alt: t.attrs.alt
				});
				let n = H({
					width: t.attrs.width,
					align: t.attrs.align
				});
				n && e.addNode("text", void 0, n);
			}
		}
	};
}), q = r("nowoImageLift", () => (() => (e) => U(e))), oe = l.extendSchema((e) => (t) => {
	let n = e(t), r = () => t.get(f.key).hostSchemes;
	return {
		...n,
		parseDOM: [{
			tag: "a[data-nowo-host-ref]",
			priority: 60,
			getAttrs: (e) => {
				let t = h(e.getAttribute("data-nowo-host-ref"), r());
				return t ? {
					href: t,
					title: e.getAttribute("title")
				} : !1;
			}
		}, ...n.parseDOM ?? []],
		toDOM: (e, t) => {
			let i = e.attrs.href;
			if (!m(i, r())) return n.toDOM(e, t);
			let [a, o] = n.toDOM(e, t), s = g(i, "file", r()), c = h(i, r()), { href: l, ...u } = o;
			return [a, {
				...u,
				...s ? { href: s } : {},
				...c ? { "data-nowo-host-ref": c } : {}
			}];
		}
	};
});
function J(e, t, n) {
	let r = document.createElement("label");
	r.className = "nowo-milkdown-image__field";
	let i = document.createElement("span");
	i.textContent = e;
	let a = document.createElement("input");
	return a.type = "text", a.maxLength = 500, a.addEventListener("change", () => t(a.value)), a.addEventListener("keydown", (e) => {
		e.key === "Escape" ? (e.preventDefault(), n()) : e.key === "Enter" && (e.preventDefault(), t(a.value));
	}), r.append(i, a), {
		wrap: r,
		input: a
	};
}
function Y(e, t, n, r, i) {
	let a = document.createElement("span");
	a.className = "nowo-milkdown-image__group", a.setAttribute("role", "group"), a.setAttribute("aria-label", e);
	let o = t.map((e) => {
		let t = document.createElement("button");
		return t.type = "button", t.className = "nowo-milkdown-image__toggle", t.textContent = n(e), t.addEventListener("click", () => r(e)), t.addEventListener("keydown", (e) => {
			e.key === "Escape" && (e.preventDefault(), i());
		}), a.append(t), {
			button: t,
			value: e
		};
	});
	return {
		group: a,
		press: (e) => o.forEach(({ button: t, value: n }) => t.setAttribute("aria-pressed", String(n === e)))
	};
}
function se(t, n, r) {
	let i = (t) => {
		let i = r(), a = i === void 0 ? null : n.state.doc.nodeAt(i);
		if (i === void 0 || !a || a.type.name !== "image") return;
		let o = n.state.tr.setNodeMarkup(i, void 0, {
			...a.attrs,
			...t
		});
		n.dispatch(o.setSelection(e.create(o.doc, i)));
	}, a = () => n.focus(), o = document.createElement("span");
	o.className = "nowo-milkdown-image__toolbar", o.contentEditable = "false";
	let s = J(d(t, "image.alt"), (e) => i({ alt: e }), a), c = J(d(t, "image.caption"), (e) => i({ title: e }), a), l = Y(d(t, "image.width"), ["", ...I], (e) => e === "" ? d(t, "image.width_original") : e, (e) => i({ width: e }), a), u = Y(d(t, "image.align"), F, (e) => d(t, `image.align_${e}`), (e) => i({ align: e }), a);
	return o.append(s.wrap, c.wrap, l.group, u.group), {
		el: o,
		focus: () => s.input.focus(),
		sync: (e) => {
			document.activeElement !== s.input && (s.input.value = e.attrs.alt), document.activeElement !== c.input && (c.input.value = e.attrs.title), l.press(L(e.attrs.width)), u.press(R(e.attrs.align));
		}
	};
}
var ce = i(K.node, (e) => (t, n, r) => {
	let i = e.get(f.key), a = i.features.has("image_block"), o = document.createElement("span"), s = document.createElement("span");
	s.className = "nowo-milkdown-image__content", o.append(s);
	let c = t, l = null, u = () => {
		let e = document.createElement("span");
		e.className = "nowo-milkdown-image__hidden", e.textContent = d(i, "image.hidden"), s.querySelector("img")?.replaceWith(e), s.contains(e) || s.prepend(e);
	}, p = () => {
		s.replaceChildren();
		let e = G(c.attrs.src, i.hostSchemes), t = a ? L(c.attrs.width) : "", n = a ? R(c.attrs.align) : "";
		if (o.className = [
			"nowo-milkdown-image",
			n ? `nowo-milkdown-image--${n}` : "",
			t.endsWith("%") ? `nowo-milkdown-image--w${t.slice(0, -1)}` : "",
			o.classList.contains("ProseMirror-selectednode") ? "ProseMirror-selectednode" : ""
		].filter(Boolean).join(" "), e) {
			let n = document.createElement("img");
			n.alt = c.attrs.alt, c.attrs.title && !a && (n.title = c.attrs.title), n.setAttribute("loading", "lazy"), t.endsWith("px") && (n.style.width = t), n.addEventListener("error", u), n.src = e, s.append(n);
		} else u();
		if (a && c.attrs.title) {
			let e = document.createElement("span");
			e.className = "nowo-milkdown-image__caption", e.textContent = c.attrs.title, s.append(e);
		}
		l?.sync(c);
	};
	return p(), {
		dom: o,
		update: (e) => {
			if (e.type !== t.type) return !1;
			let n = !e.sameMarkup(c);
			return c = e, n && p(), !0;
		},
		selectNode: () => {
			o.classList.add("ProseMirror-selectednode"), a && n.editable && !l && (l = se(i, n, r), l.sync(c), o.append(l.el));
		},
		deselectNode: () => {
			o.classList.remove("ProseMirror-selectednode"), l?.el.remove(), l = null;
		},
		stopEvent: (e) => !!l && l.el.contains(e.target),
		ignoreMutation: () => !0
	};
});
function le(t) {
	let { selection: n } = t;
	if (n instanceof e && n.node.type.name === "image") return {
		pos: n.from,
		node: n.node
	};
	let { $from: r } = n, i = r.parent.childCount === 1 ? r.parent.firstChild : null;
	return i?.type.name === "image" ? {
		pos: r.start(),
		node: i
	} : null;
}
function ue(t, n) {
	if (!t.editable || !n.features.has("image_block")) return [];
	let r = le(t.state);
	return r ? [{
		id: "image_edit",
		label: d(n, "menu.image_edit"),
		run: () => {
			t.dispatch(t.state.tr.setSelection(e.create(t.state.doc, r.pos))), queueMicrotask(() => t.dom.querySelector(".nowo-milkdown-image__toolbar input")?.focus());
		}
	}] : [];
}
var X = new n("nowo-milkdown-suggest"), de = 0, Z = /* @__PURE__ */ new WeakMap(), fe = (e) => Z.get(e)?.isOpen === !0;
function pe(e, t) {
	let { selection: n } = e;
	if (!n.empty) return null;
	let r = n.$from;
	if (!r.parent.isTextblock || r.parent.type.spec.code) return null;
	let i = r.parent.textBetween(0, r.parentOffset, void 0, "￼");
	for (let e of t) {
		e.match.lastIndex = 0;
		let t = e.match.exec(i);
		if (t) {
			let n = e.lead?.(t) ?? 0;
			return {
				trigger: e,
				from: r.pos - (t[0].length - n),
				to: r.pos,
				query: t[1] ?? ""
			};
		}
	}
	return null;
}
var me = class {
	view;
	triggers;
	runtime;
	listbox;
	live;
	active = null;
	items = [];
	index = 0;
	abort = null;
	timer;
	dismissedAt = null;
	id = `nowo-milkdown-suggest-${++de}`;
	host;
	onKeyCapture = (e) => {
		this.onKey(e) && (e.preventDefault(), e.stopImmediatePropagation());
	};
	constructor(e, t, n) {
		this.view = e, this.triggers = t, this.runtime = n, Z.set(e, this), this.listbox = document.createElement("ul"), this.listbox.id = this.id, this.listbox.className = "nowo-milkdown-suggest", this.listbox.setAttribute("role", "listbox"), this.listbox.hidden = !0, this.listbox.addEventListener("mousedown", (e) => {
			let t = e.target.closest("[role=\"option\"]");
			t && (e.preventDefault(), this.index = Number(t.dataset.index), this.pick());
		}), e.dom.addEventListener("keydown", this.onKeyCapture, !0), this.live = document.createElement("div"), this.live.className = "nowo-milkdown-sr-only", this.live.setAttribute("aria-live", "polite"), this.host = e.dom.closest(".nowo-milkdown") ?? document.body, this.host.append(this.listbox, this.live);
	}
	get isOpen() {
		return this.active !== null && !this.listbox.hidden;
	}
	update(e) {
		let t = pe(e.state, this.triggers);
		if (!t) {
			this.close();
			return;
		}
		let n = !this.active || this.active.query !== t.query || this.active.trigger !== t.trigger;
		this.active = t, n && this.schedule(), this.position();
	}
	destroy() {
		this.close(), this.view.dom.removeEventListener("keydown", this.onKeyCapture, !0), b(this.listbox), this.listbox.remove(), this.live.remove(), Z.delete(this.view);
	}
	onKey(e) {
		if (!this.isOpen) return !1;
		switch (e.key) {
			case "ArrowDown": return this.move(1), !0;
			case "ArrowUp": return this.move(-1), !0;
			case "Enter":
			case "Tab": return this.items.length ? (this.pick(), !0) : !1;
			case "Escape": return this.close(!0), !0;
			default: return !1;
		}
	}
	schedule() {
		this.dismissedAt !== this.active?.from && (this.dismissedAt = null, window.clearTimeout(this.timer), this.timer = window.setTimeout(() => void this.fetch(), 150));
	}
	async fetch() {
		let e = this.active;
		if (!e) return;
		this.abort?.abort();
		let t = new AbortController();
		this.abort = t;
		let n = !1, r = [];
		try {
			r = (await e.trigger.source(e.query, t.signal)).slice(0, 10);
		} catch (e) {
			if (e.name === "AbortError") return;
			n = !0;
		}
		t.signal.aborted || this.active !== e || (this.items = r, this.index = 0, this.render(n));
	}
	render(e) {
		let t = this.active;
		if (this.listbox.replaceChildren(), this.listbox.setAttribute("aria-label", d(this.runtime, `suggest.label.${t.trigger.name}`)), this.items.forEach((e, t) => {
			let n = document.createElement("li");
			if (n.id = `${this.id}-opt-${t}`, n.setAttribute("role", "option"), n.dataset.index = String(t), n.className = "nowo-milkdown-suggest__option", e.emoji) {
				let t = document.createElement("span");
				t.className = "nowo-milkdown-suggest__emoji", t.textContent = e.emoji, t.setAttribute("aria-hidden", "true"), n.append(t);
			}
			let r = e.icon ? p(this.runtime, e.icon) : e.glyph ? v(e.glyph, "nowo-milkdown-suggest__glyph") : null;
			r && n.append(r);
			let i = document.createElement("span");
			if (i.className = "nowo-milkdown-suggest__label", i.textContent = e.label, n.append(i), e.hint) {
				let t = document.createElement("span");
				t.className = "nowo-milkdown-suggest__hint", t.textContent = e.hint, n.append(t);
			}
			this.listbox.append(n);
		}), !this.items.length) {
			let t = document.createElement("li");
			t.className = "nowo-milkdown-suggest__empty", t.setAttribute("role", "presentation"), t.textContent = d(this.runtime, e ? "suggest.failed" : "suggest.empty"), this.listbox.append(t);
		}
		y(this.listbox, this.host);
		let n = this.view.dom;
		n.setAttribute("aria-controls", this.id), n.setAttribute("aria-autocomplete", "list"), this.live.textContent = e ? d(this.runtime, "suggest.failed") : d(this.runtime, "suggest.count", { count: this.items.length }), this.highlight(), this.position();
	}
	move(e) {
		this.items.length && (this.index = (this.index + e + this.items.length) % this.items.length, this.highlight());
	}
	highlight() {
		this.listbox.querySelectorAll("[role=\"option\"]").forEach((e, t) => e.setAttribute("aria-selected", String(t === this.index))), this.items.length ? this.view.dom.setAttribute("aria-activedescendant", `${this.id}-opt-${this.index}`) : this.view.dom.removeAttribute("aria-activedescendant"), this.listbox.querySelector("[aria-selected=\"true\"]")?.scrollIntoView({ block: "nearest" });
	}
	position() {
		if (!this.active || this.listbox.hidden) return;
		let e = this.view.coordsAtPos(this.active.from);
		ee(this.listbox, e.left, e.bottom + 4);
	}
	pick() {
		let e = this.active, t = this.items[this.index];
		e && t && (this.close(), e.trigger.apply(this.view, {
			from: e.from,
			to: e.to
		}, t), this.view.focus());
	}
	close(e = !1) {
		e && this.active && (this.dismissedAt = this.active.from), window.clearTimeout(this.timer), this.abort?.abort(), this.active = null, this.items = [], b(this.listbox);
		let t = this.view.dom;
		t.removeAttribute("aria-controls"), t.removeAttribute("aria-activedescendant"), t.removeAttribute("aria-autocomplete");
	}
};
function he(e) {
	return a((n) => new t({
		key: X,
		view: (t) => new me(t, e(n), n.get(f.key))
	}));
}
// src/Resources/assets/src/plugins/keyboard.ts
var ge = new n("nowo-milkdown-keyboard");
function Q(e) {
	let { $from: t } = e.selection;
	for (let e = t.depth; e > 0; e--) {
		let n = t.node(e).type.name;
		if (n === "table_cell" || n === "table_header") return "table";
		if (n === "list_item") return "list";
	}
	return "none";
}
function $(e) {
	e.domObserver?.flush?.();
}
function _e(e, t) {
	$(e);
	let { state: n } = e, r = Q(n), i = null;
	if (r === "table") i = u(t.shiftKey ? -1 : 1);
	else if (r === "list") {
		let e = n.schema.nodes.list_item;
		i = t.shiftKey ? s(e) : o(e);
	}
	return i !== null && i(n, e.dispatch);
}
var ve = a(() => new t({
	key: ge,
	view: (e) => {
		let t = (t) => {
			!t.isComposing && t.keyCode !== 229 && $(e), t.key !== "Tab" || t.ctrlKey || t.metaKey || t.altKey || t.defaultPrevented || fe(e) || t.target?.closest?.(".cm-editor") || (_e(e, t) && t.preventDefault(), t.stopImmediatePropagation());
		};
		return e.dom.addEventListener("keydown", t, !0), { destroy: () => e.dom.removeEventListener("keydown", t, !0) };
	}
}));
export { oe as a, q as c, M as d, ae as f, P as h, he as i, ce as l, N as m, ve as n, K as o, T as p, Q as r, ue as s, $ as t, j as u };
