import { n as e } from "./rolldown-runtime-CwxnZyOB.js";
import { B as t, C as n, D as r, E as i, F as a, G as o, H as s, I as c, J as l, K as u, L as d, M as f, N as p, O as m, P as h, Q as ee, R as te, S as ne, T as re, U as g, V as ie, W as _, X as ae, Y as v, Z as y, _ as oe, a as b, b as se, c as ce, d as le, f as ue, h as de, i as fe, j as pe, k as x, l as me, m as he, n as ge, p as _e, q as S, r as C, s as ve, t as ye, u as be, v as xe, w as Se, x as Ce, y as we, z as Te } from "./code-block-DB02z2VD.js";
import { A as Ee, E as De, L as Oe, O as w, P as T, S as ke, T as Ae, a as E, c as je, f as Me, g as Ne, j as D, l as Pe, n as Fe, o as Ie, t as Le, v as Re, w as ze, x as Be } from "./dist-BUN4Kjhx.js";
import { $t as Ve } from "./lib-9QpRoHAM.js";
import { t as He } from "./mermaid-DJO1IgvH.js";
import { a as O } from "./runtime-DBQRQGPT.js";
// node_modules/.pnpm/@codemirror+autocomplete@6.20.4/node_modules/@codemirror/autocomplete/dist/index.js
var Ue = class {
	constructor(e, t, n, r) {
		this.state = e, this.pos = t, this.explicit = n, this.view = r, this.abortListeners = [], this.abortOnDocChange = !1;
	}
	tokenBefore(e) {
		let t = D(this.state).resolveInner(this.pos, -1);
		for (; t && e.indexOf(t.name) < 0;) t = t.parent;
		return t ? {
			from: t.from,
			to: this.pos,
			text: this.state.sliceDoc(t.from, this.pos),
			type: t.type
		} : null;
	}
	matchBefore(e) {
		let t = this.state.doc.lineAt(this.pos), n = Math.max(t.from, this.pos - 250), r = t.text.slice(n - t.from, this.pos - t.from), i = r.search(Ye(e, !1));
		return i < 0 ? null : {
			from: n + i,
			to: this.pos,
			text: r.slice(i)
		};
	}
	get aborted() {
		return this.abortListeners == null;
	}
	addEventListener(e, t, n) {
		e == "abort" && this.abortListeners && (this.abortListeners.push(t), n && n.onDocChange && (this.abortOnDocChange = !0));
	}
};
function We(e) {
	let t = Object.keys(e).join(""), n = /\w/.test(t);
	return n && (t = t.replace(/\w/g, "")), `[${n ? "\\w" : ""}${t.replace(/[^\w\s]/g, "\\$&")}]`;
}
function Ge(e) {
	let t = Object.create(null), n = Object.create(null);
	for (let { label: r } of e) {
		t[r[0]] = !0;
		for (let e = 1; e < r.length; e++) n[r[e]] = !0;
	}
	let r = We(t) + We(n) + "*$";
	return [RegExp("^" + r), new RegExp(r)];
}
function Ke(e) {
	let t = e.map((e) => typeof e == "string" ? { label: e } : e), [n, r] = t.every((e) => /^\w+$/.test(e.label)) ? [/\w*$/, /\w+$/] : Ge(t);
	return (e) => {
		let i = e.matchBefore(r);
		return i || e.explicit ? {
			from: i ? i.from : e.pos,
			options: t,
			validFor: n
		} : null;
	};
}
function qe(e, t) {
	return (n) => {
		for (let t = D(n.state).resolveInner(n.pos, -1); t; t = t.parent) {
			if (e.indexOf(t.name) > -1) return null;
			if (t.type.isTop) break;
		}
		return t(n);
	};
}
var Je = class {
	constructor(e, t, n, r) {
		this.completion = e, this.source = t, this.match = n, this.score = r;
	}
};
function k(e) {
	return e.selection.main.from;
}
function Ye(e, t) {
	let { source: n } = e, r = t && n[0] != "^", i = n[n.length - 1] != "$";
	return !r && !i ? e : RegExp(`${r ? "^" : ""}(?:${n})${i ? "$" : ""}`, e.flags ?? (e.ignoreCase ? "i" : ""));
}
var Xe = /*@__PURE__*/ pe.define();
function Ze(e, t, n, r) {
	let { main: i } = e.selection, o = n - i.from, s = r - i.from;
	return {
		...e.changeByRange((c) => {
			if (c != i && n != r && e.sliceDoc(c.from + o, c.from + s) != e.sliceDoc(n, r)) return { range: c };
			let l = e.toText(t);
			return {
				changes: {
					from: c.from + o,
					to: r == i.from ? c.to : c.from + s,
					insert: l
				},
				range: a.cursor(c.from + o + l.length)
			};
		}),
		scrollIntoView: !0,
		userEvent: "input.complete"
	};
}
var Qe = /*@__PURE__*/ new WeakMap();
function $e(e) {
	if (!Array.isArray(e)) return e;
	let t = Qe.get(e);
	return t || Qe.set(e, t = Ke(e)), t;
}
var et = /*@__PURE__*/ g.define(), tt = /*@__PURE__*/ g.define(), nt = class {
	constructor(e) {
		this.pattern = e, this.chars = [], this.folded = [], this.any = [], this.precise = [], this.byWord = [], this.score = 0, this.matched = [];
		for (let t = 0; t < e.length;) {
			let n = S(e, t), r = l(n);
			this.chars.push(n);
			let i = e.slice(t, t + r), a = i.toUpperCase();
			this.folded.push(S(a == i ? i.toLowerCase() : a, 0)), t += r;
		}
		this.astral = e.length != this.chars.length;
	}
	ret(e, t) {
		return this.score = e, this.matched = t, this;
	}
	match(e) {
		if (this.pattern.length == 0) return this.ret(-100, []);
		if (e.length < this.pattern.length) return null;
		let { chars: t, folded: n, any: r, precise: i, byWord: a } = this;
		if (t.length == 1) {
			let r = S(e, 0), i = l(r), a = i == e.length ? 0 : -100;
			if (r != t[0]) {
				if (r == n[0]) a += -200;
				else return null;
			}
			return this.ret(a, [0, i]);
		}
		let o = e.indexOf(this.pattern);
		if (o == 0) return this.ret(e.length == this.pattern.length ? 0 : -100, [0, this.pattern.length]);
		let s = t.length, c = 0;
		if (o < 0) {
			for (let i = 0, a = Math.min(e.length, 200); i < a && c < s;) {
				let a = S(e, i);
				(a == t[c] || a == n[c]) && (r[c++] = i), i += l(a);
			}
			if (c < s) return null;
		}
		let u = 0, d = 0, f = !1, p = 0, m = -1, h = -1, te = /[a-z]/.test(e), ne = !0;
		for (let r = 0, c = Math.min(e.length, 200), re = 0; r < c && d < s;) {
			let c = S(e, r);
			o < 0 && (u < s && c == t[u] && (i[u++] = r), p < s && (c == t[p] || c == n[p] ? (p == 0 && (m = r), h = r + 1, p++) : p = 0));
			let g, ie = c < 255 ? c >= 48 && c <= 57 || c >= 97 && c <= 122 ? 2 : +(c >= 65 && c <= 90) : (g = ee(c)) == g.toLowerCase() ? g == g.toUpperCase() ? 0 : 2 : 1;
			(!r || ie == 1 && te || re == 0 && ie != 0) && (t[d] == c || n[d] == c && (f = !0) ? a[d++] = r : a.length && (ne = !1)), re = ie, r += l(c);
		}
		return d == s && a[0] == 0 && ne ? this.result(-100 + (f ? -200 : 0), a, e) : p == s && m == 0 ? this.ret(-200 - e.length + (h == e.length ? 0 : -100), [0, h]) : o > -1 ? this.ret(-700 - e.length, [o, o + this.pattern.length]) : p == s ? this.ret(-900 - e.length, [m, h]) : d == s ? this.result(-100 + (f ? -200 : 0) + -700 + (ne ? 0 : -1100), a, e) : t.length == 2 ? null : this.result((r[0] ? -700 : 0) + -200 + -1100, r, e);
	}
	result(e, t, n) {
		let r = [], i = 0;
		for (let e of t) {
			let t = e + (this.astral ? l(S(n, e)) : 1);
			i && r[i - 1] == e ? r[i - 1] = t : (r[i++] = e, r[i++] = t);
		}
		return this.ret(e - n.length, r);
	}
}, rt = class {
	constructor(e) {
		this.pattern = e, this.matched = [], this.score = 0, this.folded = e.toLowerCase();
	}
	match(e) {
		if (e.length < this.pattern.length) return null;
		let t = e.slice(0, this.pattern.length), n = t == this.pattern ? 0 : t.toLowerCase() == this.folded ? -200 : null;
		return n == null ? null : (this.matched = [0, t.length], this.score = n + (e.length == this.pattern.length ? 0 : -100), this);
	}
}, A = /*@__PURE__*/ d.define({ combine(e) {
	return v(e, {
		activateOnTyping: !0,
		activateOnCompletion: () => !1,
		activateOnTypingDelay: 100,
		selectOnOpen: !0,
		override: null,
		closeOnBlur: !0,
		maxRenderedOptions: 100,
		defaultKeymap: !0,
		tooltipClass: () => "",
		optionClass: () => "",
		aboveCursor: !1,
		icons: !0,
		addToOptions: [],
		positionInfo: at,
		filterStrict: !1,
		compareCompletions: (e, t) => (e.sortText || e.label).localeCompare(t.sortText || t.label),
		interactionDelay: 75,
		updateSyncTime: 100
	}, {
		defaultKeymap: (e, t) => e && t,
		closeOnBlur: (e, t) => e && t,
		icons: (e, t) => e && t,
		tooltipClass: (e, t) => (n) => it(e(n), t(n)),
		optionClass: (e, t) => (n) => it(e(n), t(n)),
		addToOptions: (e, t) => e.concat(t),
		filterStrict: (e, t) => e || t
	});
} });
function it(e, t) {
	return e ? t ? e + " " + t : e : t;
}
function at(e, t, n, r, i, a) {
	let o = e.textDirection == fe.RTL, s = o, c = !1, l = "top", u, d, f = t.left - i.left, p = i.right - t.right, m = r.right - r.left, h = r.bottom - r.top;
	if (s && f < Math.min(m, p) ? s = !1 : !s && p < Math.min(m, f) && (s = !0), m <= (s ? f : p)) u = Math.max(i.top, Math.min(n.top, i.bottom - h)) - t.top, d = Math.min(400, s ? f : p);
	else {
		c = !0, d = Math.min(400, (o ? t.right : i.right - t.left) - 30);
		let e = i.bottom - t.bottom;
		e >= h || e > t.top ? u = n.bottom - t.top : (l = "bottom", u = t.bottom - n.top);
	}
	let ee = (t.bottom - t.top) / a.offsetHeight, te = (t.right - t.left) / a.offsetWidth;
	return {
		style: `${l}: ${u / ee}px; max-width: ${d / te}px`,
		class: "cm-completionInfo-" + (c ? o ? "left-narrow" : "right-narrow" : s ? "left" : "right")
	};
}
var ot = /*@__PURE__*/ g.define();
function st(e) {
	let t = e.addToOptions.slice();
	return e.icons && t.push({
		render(e) {
			let t = document.createElement("div");
			return t.classList.add("cm-completionIcon"), e.type && t.classList.add(...e.type.split(/\s+/g).map((e) => "cm-completionIcon-" + e)), t.setAttribute("aria-hidden", "true"), t;
		},
		position: 20
	}), t.push({
		render(e, t, n, r) {
			let i = document.createElement("span");
			i.className = "cm-completionLabel";
			let a = e.displayLabel || e.label, o = 0;
			for (let e = 0; e < r.length;) {
				let t = r[e++], n = r[e++];
				t > o && i.appendChild(document.createTextNode(a.slice(o, t)));
				let s = i.appendChild(document.createElement("span"));
				s.appendChild(document.createTextNode(a.slice(t, n))), s.className = "cm-completionMatchedText", o = n;
			}
			return o < a.length && i.appendChild(document.createTextNode(a.slice(o))), i;
		},
		position: 50
	}, {
		render(e) {
			if (!e.detail) return null;
			let t = document.createElement("span");
			return t.className = "cm-completionDetail", t.textContent = e.detail, t;
		},
		position: 80
	}), t.sort((e, t) => e.position - t.position).map((e) => e.render);
}
function ct(e, t, n) {
	if (e <= n) return {
		from: 0,
		to: e
	};
	if (t < 0 && (t = 0), t <= e >> 1) {
		let e = Math.floor(t / n);
		return {
			from: e * n,
			to: (e + 1) * n
		};
	}
	let r = Math.ceil((e - t) / n);
	return {
		from: e - r * n,
		to: e - (r - 1) * n
	};
}
var lt = class {
	constructor(e, t, n) {
		this.view = e, this.stateField = t, this.applyCompletion = n, this.info = null, this.infoDestroy = null, this.placeInfoReq = {
			read: () => this.measureInfo(),
			write: (e) => this.placeInfo(e),
			key: this
		}, this.space = null, this.currentClass = "";
		let r = e.state.field(t), { options: i, selected: a } = r.open, o = e.state.facet(A);
		this.optionContent = st(o), this.optionClass = o.optionClass, this.tooltipClass = o.tooltipClass, this.range = ct(i.length, a, o.maxRenderedOptions), this.dom = document.createElement("div"), this.dom.className = "cm-tooltip-autocomplete", this.updateTooltipClass(e.state), this.dom.addEventListener("mousedown", (n) => {
			let { options: r } = e.state.field(t).open;
			for (let t = n.target, i; t && t != this.dom; t = t.parentNode) if (t.nodeName == "LI" && (i = /-(\d+)$/.exec(t.id)) && +i[1] < r.length) {
				this.applyCompletion(e, r[+i[1]]), n.preventDefault();
				return;
			}
			if (n.target == this.list) {
				let t = this.list.classList.contains("cm-completionListIncompleteTop") && n.clientY < this.list.firstChild.getBoundingClientRect().top ? this.range.from - 1 : this.list.classList.contains("cm-completionListIncompleteBottom") && n.clientY > this.list.lastChild.getBoundingClientRect().bottom ? this.range.to : null;
				t != null && (e.dispatch({ effects: ot.of(t) }), n.preventDefault());
			}
		}), this.dom.addEventListener("focusout", (t) => {
			let n = e.state.field(this.stateField, !1);
			n && n.tooltip && e.state.facet(A).closeOnBlur && t.relatedTarget != e.contentDOM && e.dispatch({ effects: tt.of(null) });
		}), this.showOptions(i, r.id);
	}
	mount() {
		this.updateSel();
	}
	showOptions(e, t) {
		this.list && this.list.remove(), this.list = this.dom.appendChild(this.createListBox(e, t, this.range)), this.list.addEventListener("scroll", () => {
			this.info && this.view.requestMeasure(this.placeInfoReq);
		});
	}
	update(e) {
		let t = e.state.field(this.stateField), n = e.startState.field(this.stateField);
		if (this.updateTooltipClass(e.state), t != n) {
			let { options: r, selected: i, disabled: a } = t.open;
			n.open && n.open.options == r || (this.range = ct(r.length, i, e.state.facet(A).maxRenderedOptions), this.showOptions(r, t.id)), this.updateSel(), a != n.open?.disabled && this.dom.classList.toggle("cm-tooltip-autocomplete-disabled", !!a);
		}
	}
	updateTooltipClass(e) {
		let t = this.tooltipClass(e);
		if (t != this.currentClass) {
			for (let e of this.currentClass.split(" ")) e && this.dom.classList.remove(e);
			for (let e of t.split(" ")) e && this.dom.classList.add(e);
			this.currentClass = t;
		}
	}
	positioned(e) {
		this.space = e, this.info && this.view.requestMeasure(this.placeInfoReq);
	}
	updateSel() {
		let e = this.view.state.field(this.stateField), t = e.open;
		(t.selected > -1 && t.selected < this.range.from || t.selected >= this.range.to) && (this.range = ct(t.options.length, t.selected, this.view.state.facet(A).maxRenderedOptions), this.showOptions(t.options, e.id));
		let r = this.updateSelectedOption(t.selected);
		if (r) {
			this.destroyInfo();
			let { completion: i } = t.options[t.selected], { info: a } = i;
			if (!a) return;
			let o = typeof a == "string" ? document.createTextNode(a) : a(i);
			if (!o) return;
			"then" in o ? o.then((t) => {
				t && this.view.state.field(this.stateField, !1) == e && this.addInfoPane(t, i);
			}).catch((e) => n(this.view.state, e, "completion info")) : (this.addInfoPane(o, i), r.setAttribute("aria-describedby", this.info.id));
		}
	}
	addInfoPane(e, t) {
		this.destroyInfo();
		let n = this.info = document.createElement("div");
		if (n.className = "cm-tooltip cm-completionInfo", n.id = "cm-completionInfo-" + Math.floor(Math.random() * 65535).toString(16), e.nodeType != null) n.appendChild(e), this.infoDestroy = null;
		else {
			let { dom: t, destroy: r } = e;
			n.appendChild(t), this.infoDestroy = r || null;
		}
		this.dom.appendChild(n), this.view.requestMeasure(this.placeInfoReq);
	}
	updateSelectedOption(e) {
		let t = null;
		for (let n = this.list.firstChild, r = this.range.from; n; n = n.nextSibling, r++) n.nodeName != "LI" || !n.id ? r-- : r == e ? n.hasAttribute("aria-selected") || (n.setAttribute("aria-selected", "true"), t = n) : n.hasAttribute("aria-selected") && (n.removeAttribute("aria-selected"), n.removeAttribute("aria-describedby"));
		return t && dt(this.list, t), t;
	}
	measureInfo() {
		let e = this.dom.querySelector("[aria-selected]");
		if (!e || !this.info) return null;
		let t = this.dom.getBoundingClientRect(), n = this.info.getBoundingClientRect(), r = e.getBoundingClientRect(), i = this.space;
		if (!i) {
			let e = this.dom.ownerDocument.documentElement;
			i = {
				left: 0,
				top: 0,
				right: e.clientWidth,
				bottom: e.clientHeight
			};
		}
		return r.top > Math.min(i.bottom, t.bottom) - 10 || r.bottom < Math.max(i.top, t.top) + 10 ? null : this.view.state.facet(A).positionInfo(this.view, t, r, n, i, this.dom);
	}
	placeInfo(e) {
		this.info && (e ? (e.style && (this.info.style.cssText = e.style), this.info.className = "cm-tooltip cm-completionInfo " + (e.class || "")) : this.info.style.cssText = "top: -1e6px");
	}
	createListBox(e, t, n) {
		let r = document.createElement("ul");
		r.id = t, r.setAttribute("role", "listbox"), r.setAttribute("aria-expanded", "true"), r.setAttribute("aria-label", this.view.state.phrase("Completions")), r.addEventListener("mousedown", (e) => {
			e.target == r && e.preventDefault();
		});
		let i = null;
		for (let a = n.from; a < n.to; a++) {
			let { completion: o, match: s } = e[a], { section: c } = o;
			if (c) {
				let e = typeof c == "string" ? c : c.name;
				if (e != i && (a > n.from || n.from == 0)) {
					if (i = e, typeof c != "string" && c.header) r.appendChild(c.header(c));
					else {
						let t = r.appendChild(document.createElement("completion-section"));
						t.textContent = e;
					}
				}
			}
			let l = r.appendChild(document.createElement("li"));
			l.id = t + "-" + a, l.setAttribute("role", "option");
			let u = this.optionClass(o);
			u && (l.className = u);
			for (let e of this.optionContent) {
				let t = e(o, this.view.state, this.view, s);
				t && l.appendChild(t);
			}
		}
		return n.from && r.classList.add("cm-completionListIncompleteTop"), n.to < e.length && r.classList.add("cm-completionListIncompleteBottom"), r;
	}
	destroyInfo() {
		this.info &&= (this.infoDestroy && this.infoDestroy(), this.info.remove(), null);
	}
	destroy() {
		this.destroyInfo();
	}
};
function ut(e, t) {
	return (n) => new lt(n, e, t);
}
function dt(e, t) {
	let n = e.getBoundingClientRect(), r = t.getBoundingClientRect(), i = n.height / e.offsetHeight;
	r.top < n.top ? e.scrollTop -= (n.top - r.top) / i : r.bottom > n.bottom && (e.scrollTop += (r.bottom - n.bottom) / i);
}
function ft(e) {
	return (e.boost || 0) * 100 + (e.apply ? 10 : 0) + (e.info ? 5 : 0) + +!!e.type;
}
function pt(e, t) {
	let n = [], r = null, i = null, a = (e) => {
		n.push(e);
		let { section: t } = e.completion;
		if (t) {
			r ||= [];
			let e = typeof t == "string" ? t : t.name;
			r.some((t) => t.name == e) || r.push(typeof t == "string" ? { name: e } : t);
		}
	}, o = t.facet(A);
	for (let r of e) if (r.hasResult()) {
		let e = r.result.getMatch;
		if (r.result.filter === !1) for (let t of r.result.options) a(new Je(t, r.source, e ? e(t) : [], 1e9 - n.length));
		else {
			let n = t.sliceDoc(r.from, r.to), s, c = o.filterStrict ? new rt(n) : new nt(n);
			for (let t of r.result.options) if (s = c.match(t.label)) {
				let n = t.displayLabel ? e ? e(t, s.matched) : [] : s.matched, o = s.score + (t.boost || 0);
				if (a(new Je(t, r.source, n, o)), typeof t.section == "object" && t.section.rank === "dynamic") {
					let { name: e } = t.section;
					i ||= Object.create(null), i[e] = Math.max(o, i[e] || -1e9);
				}
			}
		}
	}
	if (r) {
		let e = Object.create(null), t = 0, a = (e, t) => (e.rank === "dynamic" && t.rank === "dynamic" ? i[t.name] - i[e.name] : 0) || (typeof e.rank == "number" ? e.rank : 1e9) - (typeof t.rank == "number" ? t.rank : 1e9) || (e.name < t.name ? -1 : 1);
		for (let n of r.sort(a)) t -= 1e5, e[n.name] = t;
		for (let t of n) {
			let { section: n } = t.completion;
			n && (t.score += e[typeof n == "string" ? n : n.name]);
		}
	}
	let s = [], c = null, l = o.compareCompletions;
	for (let e of n.sort((e, t) => t.score - e.score || l(e.completion, t.completion))) {
		let t = e.completion;
		!c || c.label != t.label || c.detail != t.detail || c.type != null && t.type != null && c.type != t.type || c.apply != t.apply || c.boost != t.boost ? s.push(e) : ft(e.completion) > ft(c) && (s[s.length - 1] = e), c = e.completion;
	}
	return s;
}
var mt = class e {
	constructor(e, t, n, r, i, a) {
		this.options = e, this.attrs = t, this.tooltip = n, this.timestamp = r, this.selected = i, this.disabled = a;
	}
	setSelected(t, n) {
		return t == this.selected || t >= this.options.length ? this : new e(this.options, yt(n, t), this.tooltip, this.timestamp, t, this.disabled);
	}
	static build(t, n, r, i, a, o) {
		if (i && !o && t.some((e) => e.isPending)) return i.setDisabled();
		let s = pt(t, n);
		if (!s.length) return i && t.some((e) => e.isPending) ? i.setDisabled() : null;
		let c = n.facet(A).selectOnOpen ? 0 : -1;
		if (i && i.selected != c && i.selected != -1) {
			let e = i.options[i.selected].completion;
			for (let t = 0; t < s.length; t++) if (s[t].completion == e) {
				c = t;
				break;
			}
		}
		return new e(s, yt(r, c), {
			pos: t.reduce((e, t) => t.hasResult() ? Math.min(e, t.from) : e, 1e8),
			create: Et,
			above: a.aboveCursor
		}, i ? i.timestamp : Date.now(), c, !1);
	}
	map(t) {
		return new e(this.options, this.attrs, {
			...this.tooltip,
			pos: t.mapPos(this.tooltip.pos)
		}, this.timestamp, this.selected, this.disabled);
	}
	setDisabled() {
		return new e(this.options, this.attrs, this.tooltip, this.timestamp, this.selected, !0);
	}
}, ht = class e {
	constructor(e, t, n) {
		this.active = e, this.id = t, this.open = n;
	}
	static start() {
		return new e(bt, "cm-ac-" + Math.floor(Math.random() * 2e6).toString(36), null);
	}
	update(t) {
		let { state: n } = t, r = n.facet(A), i = (r.override || n.languageDataAt("autocomplete", k(n)).map($e)).map((e) => (this.active.find((t) => t.source == e) || new j(e, +!!this.active.some((e) => e.state != 0))).update(t, r));
		i.length == this.active.length && i.every((e, t) => e == this.active[t]) && (i = this.active);
		let a = this.open, o = t.effects.some((e) => e.is(wt));
		a && t.docChanged && (a = a.map(t.changes)), t.selection || i.some((e) => e.hasResult() && t.changes.touchesRange(e.from, e.to)) || !gt(i, this.active) || o ? a = mt.build(i, n, this.id, a, r, o) : a && a.disabled && !i.some((e) => e.isPending) && (a = null), !a && i.every((e) => !e.isPending) && i.some((e) => e.hasResult()) && (i = i.map((e) => e.hasResult() ? new j(e.source, 0) : e));
		for (let e of t.effects) e.is(ot) && (a &&= a.setSelected(e.value, this.id));
		return i == this.active && a == this.open ? this : new e(i, this.id, a);
	}
	get tooltip() {
		return this.open ? this.open.tooltip : null;
	}
	get attrs() {
		return this.open ? this.open.attrs : this.active.length ? _t : vt;
	}
};
function gt(e, t) {
	if (e == t) return !0;
	for (let n = 0, r = 0;;) {
		for (; n < e.length && !e[n].hasResult();) n++;
		for (; r < t.length && !t[r].hasResult();) r++;
		let i = n == e.length, a = r == t.length;
		if (i || a) return i == a;
		if (e[n++].result != t[r++].result) return !1;
	}
}
var _t = { "aria-autocomplete": "list" }, vt = {};
function yt(e, t) {
	let n = {
		"aria-autocomplete": "list",
		"aria-haspopup": "listbox",
		"aria-controls": e
	};
	return t > -1 && (n["aria-activedescendant"] = e + "-" + t), n;
}
var bt = [];
function xt(e, t) {
	if (e.isUserEvent("input.complete")) {
		let n = e.annotation(Xe);
		if (n && t.activateOnCompletion(n)) return 12;
	}
	let n = e.isUserEvent("input.type");
	return n && t.activateOnTyping ? 5 : n ? 1 : e.isUserEvent("delete.backward") ? 2 : e.selection ? 8 : e.docChanged ? 16 : 0;
}
var j = class e {
	constructor(e, t, n = !1) {
		this.source = e, this.state = t, this.explicit = n;
	}
	hasResult() {
		return !1;
	}
	get isPending() {
		return this.state == 1;
	}
	update(t, n) {
		let r = xt(t, n), i = this;
		(r & 8 || r & 16 && this.touches(t)) && (i = new e(i.source, 0)), r & 4 && i.state == 0 && (i = new e(this.source, 1)), i = i.updateFor(t, r);
		for (let n of t.effects) if (n.is(et)) i = new e(i.source, 1, n.value);
		else if (n.is(tt)) i = new e(i.source, 0);
		else if (n.is(wt)) for (let e of n.value) e.source == i.source && (i = e);
		return i;
	}
	updateFor(e, t) {
		return this.map(e.changes);
	}
	map(e) {
		return this;
	}
	touches(e) {
		return e.changes.touchesRange(k(e.state));
	}
}, St = class e extends j {
	constructor(e, t, n, r, i, a) {
		super(e, 3, t), this.limit = n, this.result = r, this.from = i, this.to = a;
	}
	hasResult() {
		return !0;
	}
	updateFor(t, n) {
		if (!(n & 3)) return this.map(t.changes);
		let r = this.result;
		r.map && !t.changes.empty && (r = r.map(r, t.changes));
		let i = t.changes.mapPos(this.from), a = t.changes.mapPos(this.to, 1), o = k(t.state);
		if (o > a || !r || n & 2 && (k(t.startState) == this.from || o < this.limit)) return new j(this.source, n & 4 ? 1 : 0);
		let s = t.changes.mapPos(this.limit);
		return Ct(r.validFor, t.state, i, a) ? new e(this.source, this.explicit, s, r, i, a) : r.update && (r = r.update(r, i, a, new Ue(t.state, o, !1))) ? new e(this.source, this.explicit, s, r, r.from, r.to ?? k(t.state)) : new j(this.source, 1, this.explicit);
	}
	map(t) {
		if (t.empty) return this;
		let n = this.result.map ? this.result.map(this.result, t) : this.result;
		return n ? new e(this.source, this.explicit, t.mapPos(this.limit), n, t.mapPos(this.from), t.mapPos(this.to, 1)) : new j(this.source, 0);
	}
	touches(e) {
		return e.changes.touchesRange(this.from, this.to);
	}
};
function Ct(e, t, n, r) {
	if (!e) return !1;
	let i = t.sliceDoc(n, r);
	return typeof e == "function" ? e(i, n, r, t) : Ye(e, !0).test(i);
}
var wt = /*@__PURE__*/ g.define({ map(e, t) {
	return e.map((e) => e.map(t));
} }), M = /*@__PURE__*/ _.define({
	create() {
		return ht.start();
	},
	update(e, t) {
		return e.update(t);
	},
	provide: (e) => [m.from(e, (e) => e.tooltip), b.contentAttributes.from(e, (e) => e.attrs)]
});
function Tt(e, t) {
	let n = t.completion.apply || t.completion.label, r = e.state.field(M).active.find((e) => e.source == t.source);
	return r instanceof St && (typeof n == "string" ? e.dispatch({
		...Ze(e.state, n, r.from, r.to),
		annotations: Xe.of(t.completion)
	}) : n(e, t.completion, r.from, r.to), !0);
}
var Et = /*@__PURE__*/ ut(M, Tt);
function Dt(e, t = "option") {
	return (n) => {
		let r = n.state.field(M, !1);
		if (!r || !r.open || r.open.disabled || Date.now() - r.open.timestamp < n.state.facet(A).interactionDelay) return !1;
		let i = 1, a;
		t == "page" && (a = de(n, r.open.tooltip)) && (i = Math.max(2, Math.floor(a.dom.offsetHeight / a.dom.querySelector("li").offsetHeight) - 1));
		let { length: o } = r.open.options, s = r.open.selected > -1 ? r.open.selected + i * (e ? 1 : -1) : e ? 0 : o - 1;
		return s < 0 ? s = t == "page" ? 0 : o - 1 : s >= o && (s = t == "page" ? o - 1 : 0), n.dispatch({ effects: ot.of(s) }), !0;
	};
}
var Ot = (e) => {
	let t = e.state.field(M, !1);
	return e.state.readOnly || !t || !t.open || t.open.selected < 0 || t.open.disabled || Date.now() - t.open.timestamp < e.state.facet(A).interactionDelay ? !1 : Tt(e, t.open.options[t.open.selected]);
}, kt = (e) => e.state.field(M, !1) ? (e.dispatch({ effects: et.of(!0) }), !0) : !1, At = (e) => {
	let t = e.state.field(M, !1);
	return !t || !t.active.some((e) => e.state != 0) ? !1 : (e.dispatch({ effects: tt.of(null) }), !0);
}, jt = class {
	constructor(e, t) {
		this.active = e, this.context = t, this.time = Date.now(), this.updates = [], this.done = void 0;
	}
}, Mt = 50, Nt = 1e3, Pt = /*@__PURE__*/ ve.fromClass(class {
	constructor(e) {
		this.view = e, this.debounceUpdate = -1, this.running = [], this.debounceAccept = -1, this.pendingStart = !1, this.composing = 0;
		for (let t of e.state.field(M).active) t.isPending && this.startQuery(t);
	}
	update(e) {
		let t = e.state.field(M), r = e.state.facet(A);
		if (!e.selectionSet && !e.docChanged && e.startState.field(M) == t) return;
		let i = e.transactions.some((e) => {
			let t = xt(e, r);
			return t & 8 || (e.selection || e.docChanged) && !(t & 3);
		});
		for (let t = 0; t < this.running.length; t++) {
			let r = this.running[t];
			if (i || r.context.abortOnDocChange && e.docChanged || r.updates.length + e.transactions.length > Mt && Date.now() - r.time > Nt) {
				for (let e of r.context.abortListeners) try {
					e();
				} catch (e) {
					n(this.view.state, e);
				}
				r.context.abortListeners = null, this.running.splice(t--, 1);
			} else r.updates.push(...e.transactions);
		}
		this.debounceUpdate > -1 && clearTimeout(this.debounceUpdate), e.transactions.some((e) => e.effects.some((e) => e.is(et))) && (this.pendingStart = !0);
		let a = this.pendingStart ? 50 : r.activateOnTypingDelay;
		if (this.debounceUpdate = t.active.some((e) => e.isPending && !this.running.some((t) => t.active.source == e.source)) ? setTimeout(() => this.startUpdate(), a) : -1, this.composing != 0) for (let t of e.transactions) t.isUserEvent("input.type") ? this.composing = 2 : this.composing == 2 && t.selection && (this.composing = 3);
	}
	startUpdate() {
		this.debounceUpdate = -1, this.pendingStart = !1;
		let { state: e } = this.view, t = e.field(M);
		for (let e of t.active) e.isPending && !this.running.some((t) => t.active.source == e.source) && this.startQuery(e);
		this.running.length && t.open && t.open.disabled && (this.debounceAccept = setTimeout(() => this.accept(), this.view.state.facet(A).updateSyncTime));
	}
	startQuery(e) {
		let { state: t } = this.view, r = new Ue(t, k(t), e.explicit, this.view), i = new jt(e, r);
		this.running.push(i), Promise.resolve(e.source(r)).then((e) => {
			i.context.aborted || (i.done = e || null, this.scheduleAccept());
		}, (e) => {
			this.view.dispatch({ effects: tt.of(null) }), n(this.view.state, e);
		});
	}
	scheduleAccept() {
		this.running.every((e) => e.done !== void 0) ? this.accept() : this.debounceAccept < 0 && (this.debounceAccept = setTimeout(() => this.accept(), this.view.state.facet(A).updateSyncTime));
	}
	accept() {
		this.debounceAccept > -1 && clearTimeout(this.debounceAccept), this.debounceAccept = -1;
		let e = [], t = this.view.state.facet(A), n = this.view.state.field(M);
		for (let r = 0; r < this.running.length; r++) {
			let i = this.running[r];
			if (i.done === void 0) continue;
			if (this.running.splice(r--, 1), i.done) {
				let n = k(i.updates.length ? i.updates[0].startState : this.view.state), r = Math.min(n, i.done.from + +!i.active.explicit), a = new St(i.active.source, i.active.explicit, r, i.done, i.done.from, i.done.to ?? n);
				for (let e of i.updates) a = a.update(e, t);
				if (a.hasResult()) {
					e.push(a);
					continue;
				}
			}
			let a = n.active.find((e) => e.source == i.active.source);
			if (a && a.isPending) {
				if (i.done == null) {
					let n = new j(i.active.source, 0);
					for (let e of i.updates) n = n.update(e, t);
					n.isPending || e.push(n);
				} else this.startQuery(a);
			}
		}
		(e.length || n.open && n.open.disabled) && this.view.dispatch({ effects: wt.of(e) });
	}
}, { eventHandlers: {
	blur(e) {
		let t = this.view.state.field(M, !1);
		if (t && t.tooltip && this.view.state.facet(A).closeOnBlur) {
			let n = t.open && de(this.view, t.open.tooltip);
			n && n.dom.contains(e.relatedTarget) || setTimeout(() => this.view.dispatch({ effects: tt.of(null) }), 10);
		}
	},
	compositionstart() {
		this.composing = 1;
	},
	compositionend() {
		this.composing == 3 && setTimeout(() => this.view.dispatch({ effects: et.of(!1) }), 20), this.composing = 0;
	}
} }), Ft = typeof navigator == "object" && /*@__PURE__*/ /Win/.test(navigator.platform), It = /*@__PURE__*/ Te.highest(/*@__PURE__*/ b.domEventHandlers({ keydown(e, t) {
	let n = t.state.field(M, !1);
	if (!n || !n.open || n.open.disabled || n.open.selected < 0 || !e.key || e.key.length > 1 || e.ctrlKey && !(Ft && e.altKey) || e.metaKey) return !1;
	let r = n.open.options[n.open.selected], i = n.active.find((e) => e.source == r.source), a = r.completion.commitCharacters || i.result.commitCharacters;
	return a && a.indexOf(e.key) > -1 && Tt(t, r), !1;
} })), Lt = /*@__PURE__*/ b.baseTheme({
	".cm-tooltip.cm-tooltip-autocomplete": { "& > ul": {
		fontFamily: "monospace",
		whiteSpace: "nowrap",
		overflow: "hidden auto",
		maxWidth_fallback: "700px",
		maxWidth: "min(700px, 95vw)",
		minWidth: "250px",
		maxHeight: "10em",
		height: "100%",
		listStyle: "none",
		margin: 0,
		padding: 0,
		"& > li, & > completion-section": {
			padding: "1px 3px",
			lineHeight: 1.2
		},
		"& > li": {
			overflowX: "hidden",
			textOverflow: "ellipsis",
			cursor: "pointer"
		},
		"& > completion-section": {
			display: "list-item",
			borderBottom: "1px solid silver",
			paddingLeft: "0.5em",
			opacity: .7
		}
	} },
	"&light .cm-tooltip-autocomplete ul li[aria-selected]": {
		background: "#17c",
		color: "white"
	},
	"&light .cm-tooltip-autocomplete-disabled ul li[aria-selected]": { background: "#777" },
	"&dark .cm-tooltip-autocomplete ul li[aria-selected]": {
		background: "#347",
		color: "white"
	},
	"&dark .cm-tooltip-autocomplete-disabled ul li[aria-selected]": { background: "#444" },
	".cm-completionListIncompleteTop:before, .cm-completionListIncompleteBottom:after": {
		content: "\"···\"",
		opacity: .5,
		display: "block",
		textAlign: "center",
		cursor: "pointer"
	},
	".cm-tooltip.cm-completionInfo": {
		position: "absolute",
		padding: "3px 9px",
		width: "max-content",
		maxWidth: "400px",
		boxSizing: "border-box",
		whiteSpace: "pre-line"
	},
	".cm-completionInfo.cm-completionInfo-left": { right: "100%" },
	".cm-completionInfo.cm-completionInfo-right": { left: "100%" },
	".cm-completionInfo.cm-completionInfo-left-narrow": { right: "30px" },
	".cm-completionInfo.cm-completionInfo-right-narrow": { left: "30px" },
	"&light .cm-snippetField": { backgroundColor: "#00000022" },
	"&dark .cm-snippetField": { backgroundColor: "#ffffff22" },
	".cm-snippetFieldPosition": {
		verticalAlign: "text-top",
		width: 0,
		height: "1.15em",
		display: "inline-block",
		margin: "0 -0.7px -.7em",
		borderLeft: "1.4px dotted #888"
	},
	".cm-completionMatchedText": { textDecoration: "underline" },
	".cm-completionDetail": {
		marginLeft: "0.5em",
		fontStyle: "italic"
	},
	".cm-completionIcon": {
		fontSize: "90%",
		width: ".8em",
		display: "inline-block",
		textAlign: "center",
		paddingRight: ".6em",
		opacity: "0.6",
		boxSizing: "content-box"
	},
	".cm-completionIcon-function, .cm-completionIcon-method": { "&:after": { content: "'ƒ'" } },
	".cm-completionIcon-class": { "&:after": { content: "'○'" } },
	".cm-completionIcon-interface": { "&:after": { content: "'◌'" } },
	".cm-completionIcon-variable": { "&:after": { content: "'𝑥'" } },
	".cm-completionIcon-constant": { "&:after": { content: "'𝐶'" } },
	".cm-completionIcon-type": { "&:after": { content: "'𝑡'" } },
	".cm-completionIcon-enum": { "&:after": { content: "'∪'" } },
	".cm-completionIcon-property": { "&:after": { content: "'□'" } },
	".cm-completionIcon-keyword": { "&:after": { content: "'🔑︎'" } },
	".cm-completionIcon-namespace": { "&:after": { content: "'▢'" } },
	".cm-completionIcon-text": { "&:after": {
		content: "'abc'",
		fontSize: "50%",
		verticalAlign: "middle"
	} }
}), Rt = class {
	constructor(e, t, n, r) {
		this.field = e, this.line = t, this.from = n, this.to = r;
	}
}, zt = class e {
	constructor(e, t, n) {
		this.field = e, this.from = t, this.to = n;
	}
	map(t) {
		let n = t.mapPos(this.from, -1, te.TrackDel), r = t.mapPos(this.to, 1, te.TrackDel);
		return n == null || r == null ? null : new e(this.field, n, r);
	}
}, Bt = class e {
	constructor(e, t) {
		this.lines = e, this.fieldPositions = t;
	}
	instantiate(e, t) {
		let n = [], r = [t], i = e.doc.lineAt(t), a = /^\s*/.exec(i.text)[0];
		for (let i of this.lines) {
			if (n.length) {
				let n = a, o = /^\t*/.exec(i)[0].length;
				for (let t = 0; t < o; t++) n += e.facet(De);
				r.push(t + n.length - o), i = n + i.slice(o);
			}
			n.push(i), t += i.length + 1;
		}
		return {
			text: n,
			ranges: this.fieldPositions.map((e) => new zt(e.field, r[e.line] + e.from, r[e.line] + e.to))
		};
	}
	static parse(t) {
		let n = [], r = [], i = [], a;
		for (let e of t.split(/\r\n?|\n/)) {
			for (; a = /[#$]\{(?:(\d+)(?::([^{}]*))?|((?:\\[{}]|[^{}])*))\}/.exec(e);) {
				let t = a[1] ? +a[1] : null, o = a[2] || a[3] || "", s = -1;
				t === 0 && (t = 1e9);
				let c = o.replace(/\\[{}]/g, (e) => e[1]);
				for (let e = 0; e < n.length; e++) (t == null ? c && n[e].name == c : n[e].seq == t) && (s = e);
				if (s < 0) {
					let e = 0;
					for (; e < n.length && (t == null || n[e].seq != null && n[e].seq < t);) e++;
					n.splice(e, 0, {
						seq: t,
						name: c
					}), s = e;
					for (let e of i) e.field >= s && e.field++;
				}
				for (let e of i) if (e.line == r.length && e.from > a.index) {
					let t = a[2] ? 3 + (a[1] || "").length : 2;
					e.from -= t, e.to -= t;
				}
				i.push(new Rt(s, r.length, a.index, a.index + c.length)), e = e.slice(0, a.index) + o + e.slice(a.index + a[0].length);
			}
			e = e.replace(/\\([{}])/g, (e, t, n) => {
				for (let e of i) e.line == r.length && e.from > n && (e.from--, e.to--);
				return t;
			}), r.push(e);
		}
		return new e(r, i);
	}
}, Vt = /*@__PURE__*/ C.widget({ widget: /*@__PURE__*/ new class extends ce {
	toDOM() {
		let e = document.createElement("span");
		return e.className = "cm-snippetFieldPosition", e;
	}
	ignoreEvent() {
		return !1;
	}
}() }), Ht = /*@__PURE__*/ C.mark({ class: "cm-snippetField" }), Ut = class e {
	constructor(e, t) {
		this.ranges = e, this.active = t, this.deco = C.set(e.map((e) => (e.from == e.to ? Vt : Ht).range(e.from, e.to)), !0);
	}
	map(t) {
		let n = [];
		for (let e of this.ranges) {
			let r = e.map(t);
			if (!r) return null;
			n.push(r);
		}
		return new e(n, this.active);
	}
	selectionInsideField(e) {
		return e.ranges.every((e) => this.ranges.some((t) => t.field == this.active && t.from <= e.from && t.to >= e.to));
	}
}, Wt = /*@__PURE__*/ g.define({ map(e, t) {
	return e && e.map(t);
} }), Gt = /*@__PURE__*/ g.define(), Kt = /*@__PURE__*/ _.define({
	create() {
		return null;
	},
	update(e, t) {
		for (let n of t.effects) {
			if (n.is(Wt)) return n.value;
			if (n.is(Gt) && e) return new Ut(e.ranges, n.value);
		}
		return e && t.docChanged && (e = e.map(t.changes)), e && t.selection && !e.selectionInsideField(t.selection) && (e = null), e;
	},
	provide: (e) => b.decorations.from(e, (e) => e ? e.deco : C.none)
});
function qt(e, t) {
	return a.create(e.filter((e) => e.field == t).map((e) => a.range(e.from, e.to)));
}
function Jt(e) {
	let t = Bt.parse(e);
	return (e, n, r, i) => {
		let { text: a, ranges: s } = t.instantiate(e.state, r), { main: c } = e.state.selection, l = {
			changes: {
				from: r,
				to: i == c.from ? c.to : i,
				insert: o.of(a)
			},
			scrollIntoView: !0,
			annotations: n ? [Xe.of(n), u.userEvent.of("input.complete")] : void 0
		};
		if (s.length && (l.selection = qt(s, 0)), s.some((e) => e.field > 0)) {
			let t = new Ut(s, 0), n = l.effects = [Wt.of(t)];
			e.state.field(Kt, !1) === void 0 && n.push(g.appendConfig.of([
				Kt,
				Qt,
				en,
				Lt
			]));
		}
		e.dispatch(e.state.update(l));
	};
}
function Yt(e) {
	return ({ state: t, dispatch: n }) => {
		let r = t.field(Kt, !1);
		if (!r || e < 0 && r.active == 0) return !1;
		let i = r.active + e, a = e > 0 && !r.ranges.some((t) => t.field == i + e);
		return n(t.update({
			selection: qt(r.ranges, i),
			effects: Wt.of(a ? null : new Ut(r.ranges, i)),
			scrollIntoView: !0
		})), !0;
	};
}
var Xt = [{
	key: "Tab",
	run: /* @__PURE__ */ Yt(1),
	shift: /* @__PURE__ */ Yt(-1)
}, {
	key: "Escape",
	run: ({ state: e, dispatch: t }) => e.field(Kt, !1) ? (t(e.update({ effects: Wt.of(null) })), !0) : !1
}], Zt = /*@__PURE__*/ d.define({ combine(e) {
	return e.length ? e[0] : Xt;
} }), Qt = /*@__PURE__*/ Te.highest(/*@__PURE__*/ Ce.compute([Zt], (e) => e.facet(Zt)));
function $t(e, t) {
	return {
		...t,
		apply: Jt(e)
	};
}
var en = /*@__PURE__*/ b.domEventHandlers({ mousedown(e, t) {
	let n = t.state.field(Kt, !1), r;
	if (!n || (r = t.posAtCoords({
		x: e.clientX,
		y: e.clientY
	})) == null) return !1;
	let i = n.ranges.find((e) => e.from <= r && e.to >= r);
	return !i || i.field == n.active ? !1 : (t.dispatch({
		selection: qt(n.ranges, i.field),
		effects: Wt.of(n.ranges.some((e) => e.field > i.field) ? new Ut(n.ranges, i.field) : null),
		scrollIntoView: !0
	}), !0);
} }), N = {
	brackets: [
		"(",
		"[",
		"{",
		"'",
		"\""
	],
	before: ")]}:;>",
	stringPrefixes: []
}, P = /*@__PURE__*/ g.define({ map(e, t) {
	return t.mapPos(e, -1, te.TrackAfter) ?? void 0;
} }), tn = /*@__PURE__*/ new class extends s {}();
tn.startSide = 1, tn.endSide = -1;
var nn = /*@__PURE__*/ _.define({
	create() {
		return t.empty;
	},
	update(e, t) {
		if (e = e.map(t.changes), t.selection) {
			let n = t.state.doc.lineAt(t.selection.main.head);
			e = e.update({ filter: (e) => e >= n.from && e <= n.to });
		}
		for (let n of t.effects) n.is(P) && (e = e.update({ add: [tn.range(n.value, n.value + 1)] }));
		return e;
	}
});
function rn() {
	return [ln, nn];
}
var an = "()[]{}<>«»»«［］｛｝";
function on(e) {
	for (let t = 0; t < 16; t += 2) if (an.charCodeAt(t) == e) return an.charAt(t + 1);
	return ee(e < 128 ? e : e + 1);
}
function sn(e, t) {
	return e.languageDataAt("closeBrackets", t)[0] || N;
}
var cn = typeof navigator == "object" && /*@__PURE__*/ /Android\b/.test(navigator.userAgent), ln = /*@__PURE__*/ b.inputHandler.of((e, t, n, r) => {
	if ((cn ? e.composing : e.compositionStarted) || e.state.readOnly) return !1;
	let i = e.state.selection.main;
	if (r.length > 2 || r.length == 2 && l(S(r, 0)) == 1 || t != i.from || n != i.to) return !1;
	let a = dn(e.state, r);
	return a ? (e.dispatch(a), !0) : !1;
}), un = [{
	key: "Backspace",
	run: ({ state: e, dispatch: t }) => {
		if (e.readOnly) return !1;
		let n = sn(e, e.selection.main.head).brackets || N.brackets, r = null, i = e.changeByRange((t) => {
			if (t.empty) {
				let r = mn(e.doc, t.head);
				for (let i of n) if (i == r && pn(e.doc, t.head) == on(S(i, 0))) return {
					changes: {
						from: t.head - i.length,
						to: t.head + i.length
					},
					range: a.cursor(t.head - i.length)
				};
			}
			return { range: r = t };
		});
		return r || t(e.update(i, {
			scrollIntoView: !0,
			userEvent: "delete.backward"
		})), !r;
	}
}];
function dn(e, t) {
	let n = sn(e, e.selection.main.head), r = n.brackets || N.brackets;
	for (let i of r) {
		let a = on(S(i, 0));
		if (t == i) return a == i ? _n(e, i, r.indexOf(i + i + i) > -1, n) : hn(e, i, a, n.before || N.before);
		if (t == a && fn(e, e.selection.main.from)) return gn(e, i, a);
	}
	return null;
}
function fn(e, t) {
	let n = !1;
	return e.field(nn).between(0, e.doc.length, (e) => {
		e == t && (n = !0);
	}), n;
}
function pn(e, t) {
	let n = e.sliceString(t, t + 2);
	return n.slice(0, l(S(n, 0)));
}
function mn(e, t) {
	let n = e.sliceString(t - 2, t);
	return l(S(n, 0)) == n.length ? n : n.slice(1);
}
function hn(e, t, n, r) {
	let i = null, o = e.changeByRange((o) => {
		if (!o.empty) return {
			changes: [{
				insert: t,
				from: o.from
			}, {
				insert: n,
				from: o.to
			}],
			effects: P.of(o.to + t.length),
			range: a.range(o.anchor + t.length, o.head + t.length)
		};
		let s = pn(e.doc, o.head);
		return !s || /\s/.test(s) || r.indexOf(s) > -1 ? {
			changes: {
				insert: t + n,
				from: o.head
			},
			effects: P.of(o.head + t.length),
			range: a.cursor(o.head + t.length)
		} : { range: i = o };
	});
	return i ? null : e.update(o, {
		scrollIntoView: !0,
		userEvent: "input.type"
	});
}
function gn(e, t, n) {
	let r = null, i = e.changeByRange((t) => t.empty && pn(e.doc, t.head) == n ? {
		changes: {
			from: t.head,
			to: t.head + n.length,
			insert: n
		},
		range: a.cursor(t.head + n.length)
	} : r = { range: t });
	return r ? null : e.update(i, {
		scrollIntoView: !0,
		userEvent: "input.type"
	});
}
function _n(e, t, n, r) {
	let i = r.stringPrefixes || N.stringPrefixes, o = null, s = e.changeByRange((r) => {
		if (!r.empty) return {
			changes: [{
				insert: t,
				from: r.from
			}, {
				insert: t,
				from: r.to
			}],
			effects: P.of(r.to + t.length),
			range: a.range(r.anchor + t.length, r.head + t.length)
		};
		let s = r.head, c = pn(e.doc, s), l;
		if (c == t) {
			if (vn(e, s)) return {
				changes: {
					insert: t + t,
					from: s
				},
				effects: P.of(s + t.length),
				range: a.cursor(s + t.length)
			};
			if (fn(e, s)) {
				let r = n && e.sliceDoc(s, s + t.length * 3) == t + t + t ? t + t + t : t;
				return {
					changes: {
						from: s,
						to: s + r.length,
						insert: r
					},
					range: a.cursor(s + r.length)
				};
			}
		} else if (n && e.sliceDoc(s - 2 * t.length, s) == t + t && (l = bn(e, s - 2 * t.length, i)) > -1 && vn(e, l)) return {
			changes: {
				insert: t + t + t + t,
				from: s
			},
			effects: P.of(s + t.length),
			range: a.cursor(s + t.length)
		};
		else if (e.charCategorizer(s)(c) != h.Word && bn(e, s, i) > -1 && !yn(e, s, t, i)) return {
			changes: {
				insert: t + t,
				from: s
			},
			effects: P.of(s + t.length),
			range: a.cursor(s + t.length)
		};
		return { range: o = r };
	});
	return o ? null : e.update(s, {
		scrollIntoView: !0,
		userEvent: "input.type"
	});
}
function vn(e, t) {
	let n = D(e).resolveInner(t + 1);
	return n.parent && n.from == t;
}
function yn(e, t, n, r) {
	let i = D(e).resolveInner(t, -1), a = r.reduce((e, t) => Math.max(e, t.length), 0);
	for (let o = 0; o < 5; o++) {
		let o = e.sliceDoc(i.from, Math.min(i.to, i.from + n.length + a)), s = o.indexOf(n);
		if (!s || s > -1 && r.indexOf(o.slice(0, s)) > -1) {
			let t = i.firstChild;
			for (; t && t.from == i.from && t.to - t.from > n.length + s;) {
				if (e.sliceDoc(t.to - n.length, t.to) == n) return !1;
				t = t.firstChild;
			}
			return !0;
		}
		let c = i.to == t && i.parent;
		if (!c) break;
		i = c;
	}
	return !1;
}
function bn(e, t, n) {
	let r = e.charCategorizer(t);
	if (r(e.sliceDoc(t - 1, t)) != h.Word) return t;
	for (let i of n) {
		let n = t - i.length;
		if (e.sliceDoc(n, t) == i && r(e.sliceDoc(n - 1, n)) != h.Word) return n;
	}
	return -1;
}
function xn(e = {}) {
	return [
		It,
		M,
		A.of(e),
		Pt,
		Cn,
		Lt
	];
}
var Sn = [
	{
		key: "Ctrl-Space",
		run: kt
	},
	{
		mac: "Alt-`",
		run: kt
	},
	{
		mac: "Alt-i",
		run: kt
	},
	{
		key: "Escape",
		run: At
	},
	{
		key: "ArrowDown",
		run: /*@__PURE__*/ Dt(!0)
	},
	{
		key: "ArrowUp",
		run: /*@__PURE__*/ Dt(!1)
	},
	{
		key: "PageDown",
		run: /*@__PURE__*/ Dt(!0, "page")
	},
	{
		key: "PageUp",
		run: /*@__PURE__*/ Dt(!1, "page")
	},
	{
		key: "Enter",
		run: Ot
	}
], Cn = /*@__PURE__*/ Te.highest(/*@__PURE__*/ Ce.computeN([A], (e) => e.facet(A).defaultKeymap ? [Sn] : [])), wn = (e) => {
	let { state: t } = e, n = t.doc.lineAt(t.selection.main.from), r = kn(e.state, n.from);
	return r.line ? En(e) : r.block ? On(e) : !1;
};
function Tn(e, t) {
	return ({ state: n, dispatch: r }) => {
		if (n.readOnly) return !1;
		let i = e(t, n);
		return i ? (r(n.update(i)), !0) : !1;
	};
}
var En = /*@__PURE__*/ Tn(Pn, 0), Dn = /*@__PURE__*/ Tn(Nn, 0), On = /*@__PURE__*/ Tn((e, t) => Nn(e, t, Mn(t)), 0);
function kn(e, t) {
	let n = e.languageDataAt("commentTokens", t, 1);
	return n.length ? n[0] : {};
}
var An = 50;
function jn(e, { open: t, close: n }, r, i) {
	let a = e.sliceDoc(r - An, r), o = e.sliceDoc(i, i + An), s = /\s*$/.exec(a)[0].length, c = /^\s*/.exec(o)[0].length, l = a.length - s;
	if (a.slice(l - t.length, l) == t && o.slice(c, c + n.length) == n) return {
		open: {
			pos: r - s,
			margin: s && 1
		},
		close: {
			pos: i + c,
			margin: c && 1
		}
	};
	let u, d;
	i - r <= 100 ? u = d = e.sliceDoc(r, i) : (u = e.sliceDoc(r, r + An), d = e.sliceDoc(i - An, i));
	let f = /^\s*/.exec(u)[0].length, p = /\s*$/.exec(d)[0].length, m = d.length - p - n.length;
	return u.slice(f, f + t.length) == t && d.slice(m, m + n.length) == n ? {
		open: {
			pos: r + f + t.length,
			margin: +!!/\s/.test(u.charAt(f + t.length))
		},
		close: {
			pos: i - p - n.length,
			margin: +!!/\s/.test(d.charAt(m - 1))
		}
	} : null;
}
function Mn(e) {
	let t = [];
	for (let n of e.selection.ranges) {
		let r = e.doc.lineAt(n.from), i = n.to <= r.to ? r : e.doc.lineAt(n.to);
		i.from > r.from && i.from == n.to && (i = n.to == r.to + 1 ? r : e.doc.lineAt(n.to - 1));
		let a = t.length - 1;
		a >= 0 && t[a].to > r.from ? t[a].to = i.to : t.push({
			from: r.from + /^\s*/.exec(r.text)[0].length,
			to: i.to
		});
	}
	return t;
}
function Nn(e, t, n = t.selection.ranges) {
	let r = n.map((e) => kn(t, e.from).block);
	if (!r.every((e) => e)) return null;
	let i = n.map((e, n) => jn(t, r[n], e.from, e.to));
	if (e != 2 && !i.every((e) => e)) return { changes: t.changes(n.map((e, t) => i[t] ? [] : [{
		from: e.from,
		insert: r[t].open + " "
	}, {
		from: e.to,
		insert: " " + r[t].close
	}])) };
	if (e != 1 && i.some((e) => e)) {
		let e = [];
		for (let t = 0, n; t < i.length; t++) if (n = i[t]) {
			let i = r[t], { open: a, close: o } = n;
			e.push({
				from: a.pos - i.open.length,
				to: a.pos + a.margin
			}, {
				from: o.pos - o.margin,
				to: o.pos + i.close.length
			});
		}
		return { changes: e };
	}
	return null;
}
function Pn(e, t, n = t.selection.ranges) {
	let r = [], i = -1;
	ranges: for (let { from: e, to: a } of n) {
		let n = r.length, o = 1e9, s;
		for (let n = e; n <= a;) {
			let c = t.doc.lineAt(n);
			if (s == null && (s = kn(t, c.from).line, !s)) continue ranges;
			if (c.from > i && (e == a || a > c.from)) {
				i = c.from;
				let e = /^\s*/.exec(c.text)[0].length, t = e == c.length, n = c.text.slice(e, e + s.length) == s ? e : -1;
				e < c.text.length && e < o && (o = e), r.push({
					line: c,
					comment: n,
					token: s,
					indent: e,
					empty: t,
					single: !1
				});
			}
			n = c.to + 1;
		}
		if (o < 1e9) for (let e = n; e < r.length; e++) r[e].indent < r[e].line.text.length && (r[e].indent = o);
		r.length == n + 1 && (r[n].single = !0);
	}
	if (e != 2 && r.some((e) => e.comment < 0 && (!e.empty || e.single))) {
		let e = [];
		for (let { line: t, token: n, indent: i, empty: a, single: o } of r) (o || !a) && e.push({
			from: t.from + i,
			insert: n + " "
		});
		let n = t.changes(e);
		return {
			changes: n,
			selection: t.selection.map(n, 1)
		};
	}
	if (e != 1 && r.some((e) => e.comment >= 0)) {
		let e = [];
		for (let { line: t, comment: n, token: i } of r) if (n >= 0) {
			let r = t.from + n, a = r + i.length;
			t.text[a - t.from] == " " && a++, e.push({
				from: r,
				to: a
			});
		}
		return { changes: e };
	}
	return null;
}
var Fn = /*@__PURE__*/ pe.define(), In = /*@__PURE__*/ pe.define(), Ln = /*@__PURE__*/ d.define(), Rn = /*@__PURE__*/ d.define({ combine(e) {
	return v(e, {
		minDepth: 100,
		newGroupDelay: 500,
		joinToEvent: (e, t) => t
	}, {
		minDepth: Math.max,
		newGroupDelay: Math.min,
		joinToEvent: (e, t) => (n, r) => e(n, r) || t(n, r)
	});
} }), zn = /*@__PURE__*/ _.define({
	create() {
		return nr.empty;
	},
	update(e, t) {
		let n = t.state.facet(Rn), r = t.annotation(Fn);
		if (r) {
			let i = F.fromTransaction(t, r.selection), a = r.side, o = a == 0 ? e.undone : e.done;
			return o = i ? Kn(o, o.length, n.minDepth, i) : Zn(o, t.startState.selection), new nr(a == 0 ? r.rest : o, a == 0 ? o : r.rest);
		}
		let i = t.annotation(In);
		if ((i == "full" || i == "before") && (e = e.isolate()), t.annotation(u.addToHistory) === !1) return t.changes.empty ? e : e.addMapping(t.changes.desc);
		let a = F.fromTransaction(t), o = t.annotation(u.time), s = t.annotation(u.userEvent);
		return a ? e = e.addChanges(a, o, s, n, t) : t.selection && (e = e.addSelection(t.startState.selection, o, s, n.newGroupDelay)), (i == "full" || i == "after") && (e = e.isolate()), e;
	},
	toJSON(e) {
		return {
			done: e.done.map((e) => e.toJSON()),
			undone: e.undone.map((e) => e.toJSON())
		};
	},
	fromJSON(e) {
		return new nr(e.done.map(F.fromJSON), e.undone.map(F.fromJSON));
	}
});
function Bn(e = {}) {
	return [
		zn,
		Rn.of(e),
		b.domEventHandlers({ beforeinput(e, t) {
			let n = e.inputType == "historyUndo" ? Hn : e.inputType == "historyRedo" ? Un : null;
			return n ? (e.preventDefault(), n(t)) : !1;
		} })
	];
}
function Vn(e, t) {
	return function({ state: n, dispatch: r }) {
		if (!t && n.readOnly) return !1;
		let i = n.field(zn, !1);
		if (!i) return !1;
		let a = i.pop(e, n, t);
		return a ? (r(a), !0) : !1;
	};
}
var Hn = /*@__PURE__*/ Vn(0, !1), Un = /*@__PURE__*/ Vn(1, !1), Wn = /*@__PURE__*/ Vn(0, !0), Gn = /*@__PURE__*/ Vn(1, !0), F = class e {
	constructor(e, t, n, r, i) {
		this.changes = e, this.effects = t, this.mapped = n, this.startSelection = r, this.selectionsAfter = i;
	}
	setSelAfter(t) {
		return new e(this.changes, this.effects, this.mapped, this.startSelection, t);
	}
	toJSON() {
		return {
			changes: this.changes?.toJSON(),
			mapped: this.mapped?.toJSON(),
			startSelection: this.startSelection?.toJSON(),
			selectionsAfter: this.selectionsAfter.map((e) => e.toJSON())
		};
	}
	static fromJSON(t) {
		return new e(t.changes && p.fromJSON(t.changes), [], t.mapped && f.fromJSON(t.mapped), t.startSelection && a.fromJSON(t.startSelection), t.selectionsAfter.map(a.fromJSON));
	}
	static fromTransaction(t, n) {
		let r = I;
		for (let e of t.startState.facet(Ln)) {
			let n = e(t);
			n.length && (r = r.concat(n));
		}
		return !r.length && t.changes.empty ? null : new e(t.changes.invert(t.startState.doc), r, void 0, n || t.startState.selection, I);
	}
	static selection(t) {
		return new e(void 0, I, void 0, void 0, t);
	}
};
function Kn(e, t, n, r) {
	let i = t + 1 > n + 20 ? t - n - 1 : 0, a = e.slice(i, t);
	return a.push(r), a;
}
function qn(e, t) {
	let n = [], r = !1;
	return e.iterChangedRanges((e, t) => n.push(e, t)), t.iterChangedRanges((e, t, i, a) => {
		for (let e = 0; e < n.length;) {
			let t = n[e++], o = n[e++];
			a >= t && i <= o && (r = !0);
		}
	}), r;
}
function Jn(e, t) {
	return e.ranges.length == t.ranges.length && e.ranges.filter((e, n) => e.empty != t.ranges[n].empty).length === 0;
}
function Yn(e, t) {
	return e.length ? t.length ? e.concat(t) : e : t;
}
var I = [], Xn = 200;
function Zn(e, t) {
	if (e.length) {
		let n = e[e.length - 1], r = n.selectionsAfter.slice(Math.max(0, n.selectionsAfter.length - Xn));
		return r.length && r[r.length - 1].eq(t) ? e : (r.push(t), Kn(e, e.length - 1, 1e9, n.setSelAfter(r)));
	}
	return [F.selection([t])];
}
function Qn(e) {
	let t = e[e.length - 1], n = e.slice();
	return n[e.length - 1] = t.setSelAfter(t.selectionsAfter.slice(0, t.selectionsAfter.length - 1)), n;
}
function $n(e, t) {
	if (!e.length) return e;
	let n = e.length, r = I;
	for (; n;) {
		let i = er(e[n - 1], t, r);
		if (i.changes && !i.changes.empty || i.effects.length) {
			let t = e.slice(0, n);
			return t[n - 1] = i, t;
		}
		t = i.mapped, n--, r = i.selectionsAfter;
	}
	return r.length ? [F.selection(r)] : I;
}
function er(e, t, n) {
	let r = Yn(e.selectionsAfter.length ? e.selectionsAfter.map((e) => e.map(t)) : I, n);
	if (!e.changes) return F.selection(r);
	let i = e.changes.map(t), a = t.mapDesc(e.changes, !0), o = e.mapped ? e.mapped.composeDesc(a) : a;
	return new F(i, g.mapEffects(e.effects, t), o, e.startSelection.map(a), r);
}
var tr = /^(input\.type|delete)($|\.)/, nr = class e {
	constructor(e, t, n = 0, r = void 0) {
		this.done = e, this.undone = t, this.prevTime = n, this.prevUserEvent = r;
	}
	isolate() {
		return this.prevTime ? new e(this.done, this.undone) : this;
	}
	addChanges(t, n, r, i, a) {
		let o = this.done, s = o[o.length - 1];
		return o = s && s.changes && !s.changes.empty && t.changes && (!r || tr.test(r)) && (!s.selectionsAfter.length && n - this.prevTime < i.newGroupDelay && i.joinToEvent(a, qn(s.changes, t.changes)) || r == "input.type.compose") ? Kn(o, o.length - 1, i.minDepth, new F(t.changes.compose(s.changes), Yn(g.mapEffects(t.effects, s.changes), s.effects), s.mapped, s.startSelection, I)) : Kn(o, o.length, i.minDepth, t), new e(o, I, n, r);
	}
	addSelection(t, n, r, i) {
		let a = this.done.length ? this.done[this.done.length - 1].selectionsAfter : I;
		return a.length > 0 && n - this.prevTime < i && r == this.prevUserEvent && r && /^select($|\.)/.test(r) && Jn(a[a.length - 1], t) ? this : new e(Zn(this.done, t), this.undone, n, r);
	}
	addMapping(t) {
		return new e($n(this.done, t), $n(this.undone, t), this.prevTime, this.prevUserEvent);
	}
	pop(e, t, n) {
		let r = e == 0 ? this.done : this.undone;
		if (r.length == 0) return null;
		let i = r[r.length - 1], a = i.selectionsAfter[0] || (i.startSelection ? i.startSelection.map(i.changes.invertedDesc, 1) : t.selection);
		if (n && i.selectionsAfter.length) return t.update({
			selection: i.selectionsAfter[i.selectionsAfter.length - 1],
			annotations: Fn.of({
				side: e,
				rest: Qn(r),
				selection: a
			}),
			userEvent: e == 0 ? "select.undo" : "select.redo",
			scrollIntoView: !0
		});
		if (i.changes) {
			let n = r.length == 1 ? I : r.slice(0, r.length - 1);
			return i.mapped && (n = $n(n, i.mapped)), t.update({
				changes: i.changes,
				selection: i.startSelection,
				effects: i.effects,
				annotations: Fn.of({
					side: e,
					rest: n,
					selection: a
				}),
				filter: !1,
				userEvent: e == 0 ? "undo" : "redo",
				scrollIntoView: !0
			});
		}
		return null;
	}
};
nr.empty = /*@__PURE__*/ new nr(I, I);
var rr = [
	{
		key: "Mod-z",
		run: Hn,
		preventDefault: !0
	},
	{
		key: "Mod-y",
		mac: "Mod-Shift-z",
		run: Un,
		preventDefault: !0
	},
	{
		linux: "Ctrl-Shift-z",
		run: Un,
		preventDefault: !0
	},
	{
		key: "Mod-u",
		run: Wn,
		preventDefault: !0
	},
	{
		key: "Alt-u",
		mac: "Mod-Shift-u",
		run: Gn,
		preventDefault: !0
	}
];
function L(e, t) {
	return a.create(e.ranges.map(t), e.mainIndex);
}
function R(e, t) {
	return e.update({
		selection: t,
		scrollIntoView: !0,
		userEvent: "select"
	});
}
function z({ state: e, dispatch: t }, n) {
	let r = L(e.selection, n);
	return !r.eq(e.selection, !0) && (t(R(e, r)), !0);
}
function ir(e, t) {
	return a.cursor(t ? e.to : e.from);
}
function ar(e, t) {
	return z(e, (n) => n.empty ? e.moveByChar(n, t) : ir(n, t));
}
function B(e) {
	return e.textDirectionAt(e.state.selection.main.head) == fe.LTR;
}
var or = (e) => ar(e, !B(e)), sr = (e) => ar(e, B(e));
function cr(e, t) {
	return z(e, (n) => n.empty ? e.moveByGroup(n, t) : ir(n, t));
}
var lr = (e) => cr(e, !B(e)), ur = (e) => cr(e, B(e));
typeof Intl < "u" && Intl.Segmenter;
function dr(e, t, n) {
	if (t.type.prop(n)) return !0;
	let r = t.to - t.from;
	return r && (r > 2 || /[^\s,.;:]/.test(e.sliceDoc(t.from, t.to))) || t.firstChild;
}
function fr(e, t, n) {
	let r = D(e).resolveInner(t.head), i = n ? Oe.closedBy : Oe.openedBy;
	for (let a = t.head;;) {
		let t = n ? r.childAfter(a) : r.childBefore(a);
		if (!t) break;
		dr(e, t, i) ? r = t : a = n ? t.to : t.from;
	}
	let o = r.type.prop(i), s, c;
	return c = o && (s = n ? w(e, r.from, 1) : w(e, r.to, -1)) && s.matched ? n ? s.end.to : s.end.from : n ? r.to : r.from, a.cursor(c, n ? -1 : 1);
}
var pr = (e) => z(e, (t) => fr(e.state, t, !B(e))), mr = (e) => z(e, (t) => fr(e.state, t, B(e)));
function hr(e, t) {
	return z(e, (n) => {
		if (!n.empty) return ir(n, t);
		let r = e.moveVertically(n, t);
		return r.head == n.head ? e.moveToLineBoundary(n, t) : r;
	});
}
var gr = (e) => hr(e, !1), _r = (e) => hr(e, !0);
function vr(e) {
	let t = e.scrollDOM.clientHeight < e.scrollDOM.scrollHeight - 2, n = 0, r = 0, i;
	if (t) {
		for (let t of e.state.facet(b.scrollMargins)) {
			let i = t(e);
			i?.top && (n = Math.max(i?.top, n)), i?.bottom && (r = Math.max(i?.bottom, r));
		}
		i = e.scrollDOM.clientHeight - n - r;
	} else i = (e.dom.ownerDocument.defaultView || window).innerHeight;
	return {
		marginTop: n,
		marginBottom: r,
		selfScroll: t,
		height: Math.max(e.defaultLineHeight, i - 5)
	};
}
function yr(e, t) {
	let n = vr(e), { state: r } = e, i = L(r.selection, (r) => r.empty ? e.moveVertically(r, t, n.height) : ir(r, t));
	if (i.eq(r.selection)) return !1;
	let a;
	if (n.selfScroll) {
		let t = e.coordsAtPos(r.selection.main.head), o = e.scrollDOM.getBoundingClientRect(), s = o.top + n.marginTop, c = o.bottom - n.marginBottom;
		t && t.top > s && t.bottom < c && (a = b.scrollIntoView(i.main.head, {
			y: "start",
			yMargin: t.top - s
		}));
	}
	return e.dispatch(R(r, i), { effects: a }), !0;
}
var br = (e) => yr(e, !1), xr = (e) => yr(e, !0);
function V(e, t, n) {
	let r = e.lineBlockAt(t.head), i = e.moveToLineBoundary(t, n);
	if (i.head == t.head && i.head != (n ? r.to : r.from) && (i = e.moveToLineBoundary(t, n, !1)), !n && i.head == r.from && r.length) {
		let n = /^\s*/.exec(e.state.sliceDoc(r.from, Math.min(r.from + 100, r.to)))[0].length;
		n && t.head != r.from + n && (i = a.cursor(r.from + n));
	}
	return i;
}
var Sr = (e) => z(e, (t) => V(e, t, !0)), Cr = (e) => z(e, (t) => V(e, t, !1)), wr = (e) => z(e, (t) => V(e, t, !B(e))), Tr = (e) => z(e, (t) => V(e, t, B(e))), Er = (e) => z(e, (t) => e.moveToLineBoundary(t, !1, !1)), Dr = (e) => z(e, (t) => e.moveToLineBoundary(t, !0, !1));
function Or(e, t, n) {
	let r = !1, i = L(e.selection, (t) => {
		let i = w(e, t.head, -1) || w(e, t.head, 1) || t.head > 0 && w(e, t.head - 1, 1) || t.head < e.doc.length && w(e, t.head + 1, -1);
		if (!i || !i.end) return t;
		r = !0;
		let o = i.start.from == t.head ? i.end.to : i.end.from;
		return n ? a.range(t.anchor, o) : a.cursor(o);
	});
	return r ? (t(R(e, i)), !0) : !1;
}
var kr = ({ state: e, dispatch: t }) => Or(e, t, !1);
function H(e, t, n) {
	let r = L(e.state.selection, (e) => {
		e.undirectional && e.head >= e.anchor != t && (e = a.range(e.head, e.anchor));
		let r = n(e);
		return a.range(e.anchor, r.head, r.goalColumn, r.bidiLevel || void 0, r.assoc);
	});
	return !r.eq(e.state.selection) && (e.dispatch(R(e.state, r)), !0);
}
function Ar(e, t) {
	return H(e, t, (n) => e.moveByChar(n, t));
}
var jr = (e) => Ar(e, !B(e)), Mr = (e) => Ar(e, B(e));
function Nr(e, t) {
	return H(e, t, (n) => e.moveByGroup(n, t));
}
var Pr = (e) => Nr(e, !B(e)), Fr = (e) => Nr(e, B(e)), Ir = (e) => {
	let t = !B(e);
	return H(e, t, (n) => fr(e.state, n, t));
}, Lr = (e) => {
	let t = B(e);
	return H(e, t, (n) => fr(e.state, n, t));
};
function Rr(e, t) {
	return H(e, t, (n) => e.moveVertically(n, t));
}
var zr = (e) => Rr(e, !1), Br = (e) => Rr(e, !0);
function Vr(e, t) {
	return H(e, t, (n) => e.moveVertically(n, t, vr(e).height));
}
var Hr = (e) => Vr(e, !1), Ur = (e) => Vr(e, !0), Wr = (e) => H(e, !0, (t) => V(e, t, !0)), Gr = (e) => H(e, !1, (t) => V(e, t, !1)), Kr = (e) => {
	let t = !B(e);
	return H(e, t, (n) => V(e, n, t));
}, qr = (e) => {
	let t = B(e);
	return H(e, t, (n) => V(e, n, t));
}, Jr = (e) => H(e, !1, (t) => a.cursor(e.lineBlockAt(t.head).from)), Yr = (e) => H(e, !0, (t) => a.cursor(e.lineBlockAt(t.head).to)), Xr = ({ state: e, dispatch: t }) => (t(R(e, { anchor: 0 })), !0), Zr = ({ state: e, dispatch: t }) => (t(R(e, { anchor: e.doc.length })), !0), Qr = ({ state: e, dispatch: t }) => (t(R(e, {
	anchor: e.selection.main.anchor,
	head: 0
})), !0), $r = ({ state: e, dispatch: t }) => (t(R(e, {
	anchor: e.selection.main.anchor,
	head: e.doc.length
})), !0), ei = ({ state: e, dispatch: t }) => (t(e.update({
	selection: {
		anchor: 0,
		head: e.doc.length
	},
	userEvent: "select"
})), !0), ti = ({ state: e, dispatch: t }) => {
	let n = bi(e).map(({ from: t, to: n }) => a.undirectionalRange(t, Math.min(n + 1, e.doc.length)));
	return t(e.update({
		selection: a.create(n),
		userEvent: "select"
	})), !0;
}, ni = ({ state: e, dispatch: t }) => {
	let n = L(e.selection, (t) => {
		let n = D(e), r = n.resolveStack(t.from, 1);
		if (t.empty) {
			let e = n.resolveStack(t.from, -1);
			e.node.from >= r.node.from && e.node.to <= r.node.to && (r = e);
		}
		for (let e = r; e; e = e.next) {
			let { node: n } = e;
			if ((n.from < t.from && n.to >= t.to || n.to > t.to && n.from <= t.from) && e.next) return a.undirectionalRange(n.from, n.to);
		}
		return t;
	});
	return !n.eq(e.selection) && (t(R(e, n)), !0);
};
function ri(e, t) {
	let { state: n } = e, r = n.selection, i = n.selection.ranges.slice();
	for (let r of n.selection.ranges) {
		let a = n.doc.lineAt(r.head);
		if (t ? a.to < e.state.doc.length : a.from > 0) for (let n = r;;) {
			let r = e.moveVertically(n, t);
			if (r.head < a.from || r.head > a.to) {
				i.some((e) => e.head == r.head) || i.push(r);
				break;
			}
			if (r.head == n.head) break;
			n = r;
		}
	}
	return i.length != r.ranges.length && (e.dispatch(R(n, a.create(i, i.length - 1))), !0);
}
var ii = (e) => ri(e, !1), ai = (e) => ri(e, !0), oi = ({ state: e, dispatch: t }) => {
	let n = e.selection, r = null;
	return n.ranges.length > 1 ? r = a.create([n.main]) : n.main.empty || (r = a.create([a.cursor(n.main.head)])), r ? (t(R(e, r)), !0) : !1;
};
function si(e, t) {
	if (e.state.readOnly) return !1;
	let n = "delete.selection", { state: r } = e, i = r.changeByRange((r) => {
		let { from: i, to: o } = r;
		if (i == o) {
			let a = t(r);
			a < i ? (n = "delete.backward", a = ci(e, a, !1)) : a > i && (n = "delete.forward", a = ci(e, a, !0)), i = Math.min(i, a), o = Math.max(o, a);
		} else i = ci(e, i, !1), o = ci(e, o, !0);
		return i == o ? { range: r } : {
			changes: {
				from: i,
				to: o
			},
			range: a.cursor(i, i < r.head ? -1 : 1)
		};
	});
	return !i.changes.empty && (e.dispatch(r.update(i, {
		scrollIntoView: !0,
		userEvent: n,
		effects: n == "delete.selection" ? b.announce.of(r.phrase("Selection deleted")) : void 0
	})), !0);
}
function ci(e, t, n) {
	if (e instanceof b) for (let r of e.state.facet(b.atomicRanges).map((t) => t(e))) r.between(t, t, (e, r) => {
		e < t && r > t && (t = n ? r : e);
	});
	return t;
}
var li = (e, t, n) => si(e, (r) => {
	let i = r.from, { state: a } = e, o = a.doc.lineAt(i), s, c;
	if (n && !t && i > o.from && i < o.from + 200 && !/[^ \t]/.test(s = o.text.slice(0, i - o.from))) {
		if (s[s.length - 1] == "	") return i - 1;
		let e = ae(s, a.tabSize) % Be(a) || Be(a);
		for (let t = 0; t < e && s[s.length - 1 - t] == " "; t++) i--;
		c = i;
	} else c = y(o.text, i - o.from, t, t) + o.from, c == i && o.number != (t ? a.doc.lines : 1) ? c += t ? 1 : -1 : !t && /[\ufe00-\ufe0f]/.test(o.text.slice(c - o.from, i - o.from)) && (c = y(o.text, c - o.from, !1, !1) + o.from);
	return c;
}), ui = (e) => li(e, !1, !0), di = (e) => li(e, !0, !1), fi = (e, t) => si(e, (n) => {
	let r = n.head, { state: i } = e, a = i.doc.lineAt(r), o = i.charCategorizer(r);
	for (let e = null;;) {
		if (r == (t ? a.to : a.from)) {
			r == n.head && a.number != (t ? i.doc.lines : 1) && (r += t ? 1 : -1);
			break;
		}
		let s = y(a.text, r - a.from, t) + a.from, c = a.text.slice(Math.min(r, s) - a.from, Math.max(r, s) - a.from), l = o(c);
		if (e != null && l != e) break;
		(c != " " || r != n.head) && (e = l), r = s;
	}
	return r;
}), pi = (e) => fi(e, !1), mi = (e) => fi(e, !0), hi = (e) => si(e, (t) => {
	let n = e.lineBlockAt(t.head).to;
	return t.head < n ? n : Math.min(e.state.doc.length, t.head + 1);
}), gi = (e) => si(e, (t) => {
	let n = e.moveToLineBoundary(t, !1).head;
	return t.head > n ? n : Math.max(0, t.head - 1);
}), _i = (e) => si(e, (t) => {
	let n = e.moveToLineBoundary(t, !0).head;
	return t.head < n ? n : Math.min(e.state.doc.length, t.head + 1);
}), vi = ({ state: e, dispatch: t }) => {
	if (e.readOnly) return !1;
	let n = e.changeByRange((e) => ({
		changes: {
			from: e.from,
			to: e.to,
			insert: o.of(["", ""])
		},
		range: a.cursor(e.from)
	}));
	return t(e.update(n, {
		scrollIntoView: !0,
		userEvent: "input"
	})), !0;
}, yi = ({ state: e, dispatch: t }) => {
	if (e.readOnly) return !1;
	let n = e.changeByRange((t) => {
		if (!t.empty || t.from == 0 || t.from == e.doc.length) return { range: t };
		let n = t.from, r = e.doc.lineAt(n), i = n == r.from ? n - 1 : y(r.text, n - r.from, !1) + r.from, o = n == r.to ? n + 1 : y(r.text, n - r.from, !0) + r.from;
		return {
			changes: {
				from: i,
				to: o,
				insert: e.doc.slice(n, o).append(e.doc.slice(i, n))
			},
			range: a.cursor(o)
		};
	});
	return !n.changes.empty && (t(e.update(n, {
		scrollIntoView: !0,
		userEvent: "move.character"
	})), !0);
};
function bi(e) {
	let t = [], n = -1;
	for (let r of e.selection.ranges) {
		let i = e.doc.lineAt(r.from), a = e.doc.lineAt(r.to);
		if (!r.empty && r.to == a.from && (a = e.doc.lineAt(r.to - 1)), n >= i.number) {
			let e = t[t.length - 1];
			e.to = a.to, e.ranges.push(r);
		} else t.push({
			from: i.from,
			to: a.to,
			ranges: [r]
		});
		n = a.number + 1;
	}
	return t;
}
function xi(e, t, n) {
	if (e.readOnly) return !1;
	let r = [], i = [];
	for (let t of bi(e)) {
		if (n ? t.to == e.doc.length : t.from == 0) continue;
		let o = e.doc.lineAt(n ? t.to + 1 : t.from - 1), s = o.length + 1;
		if (n) {
			r.push({
				from: t.to,
				to: o.to
			}, {
				from: t.from,
				insert: o.text + e.lineBreak
			});
			for (let n of t.ranges) i.push(a.range(Math.min(e.doc.length, n.anchor + s), Math.min(e.doc.length, n.head + s)));
		} else {
			r.push({
				from: o.from,
				to: t.from
			}, {
				from: t.to,
				insert: e.lineBreak + o.text
			});
			for (let e of t.ranges) i.push(a.range(e.anchor - s, e.head - s));
		}
	}
	return r.length ? (t(e.update({
		changes: r,
		scrollIntoView: !0,
		selection: a.create(i, e.selection.mainIndex),
		userEvent: "move.line"
	})), !0) : !1;
}
var Si = ({ state: e, dispatch: t }) => xi(e, t, !1), Ci = ({ state: e, dispatch: t }) => xi(e, t, !0);
function wi(e, t, n) {
	if (e.readOnly) return !1;
	let r = [];
	for (let t of bi(e)) n ? r.push({
		from: t.from,
		insert: e.doc.slice(t.from, t.to) + e.lineBreak
	}) : r.push({
		from: t.to,
		insert: e.lineBreak + e.doc.slice(t.from, t.to)
	});
	let i = e.changes(r);
	return t(e.update({
		changes: i,
		selection: e.selection.map(i, n ? 1 : -1),
		scrollIntoView: !0,
		userEvent: "input.copyline"
	})), !0;
}
var Ti = ({ state: e, dispatch: t }) => wi(e, t, !1), Ei = ({ state: e, dispatch: t }) => wi(e, t, !0), Di = (e) => {
	if (e.state.readOnly) return !1;
	let { state: t } = e, n = t.changes(bi(t).map(({ from: e, to: n }) => (e > 0 ? e-- : n < t.doc.length && n++, {
		from: e,
		to: n
	}))), r = L(t.selection, (t) => {
		let n;
		if (e.lineWrapping) {
			let r = e.lineBlockAt(t.head), i = e.coordsAtPos(t.head, t.assoc || 1);
			i && (n = r.bottom + e.documentTop - i.bottom + e.defaultLineHeight / 2);
		}
		return e.moveVertically(t, !0, n);
	}).map(n);
	return e.dispatch({
		changes: n,
		selection: r,
		scrollIntoView: !0,
		userEvent: "delete.line"
	}), !0;
};
function Oi(e, t) {
	if (/\(\)|\[\]|\{\}/.test(e.sliceDoc(t - 1, t + 1))) return {
		from: t,
		to: t
	};
	let n = D(e).resolveInner(t), r = n.childBefore(t), i = n.childAfter(t), a;
	return r && i && r.to <= t && i.from >= t && (a = r.type.prop(Oe.closedBy)) && a.indexOf(i.name) > -1 && e.doc.lineAt(r.to).from == e.doc.lineAt(i.from).from && !/\S/.test(e.sliceDoc(r.to, i.from)) ? {
		from: r.to,
		to: i.from
	} : null;
}
var ki = /*@__PURE__*/ ji(!1), Ai = /*@__PURE__*/ ji(!0);
function ji(e) {
	return ({ state: t, dispatch: n }) => {
		if (t.readOnly) return !1;
		let r = t.changeByRange((n) => {
			let { from: r, to: i } = n, s = t.doc.lineAt(r), c = !e && r == i && Oi(t, r);
			e && (r = i = (i <= s.to ? s : t.doc.lineAt(i)).to);
			let l = new Fe(t, {
				simulateBreak: r,
				simulateDoubleBreak: !!c
			}), u = ke(l, r);
			for (u ??= ae(/^\s*/.exec(t.doc.lineAt(r).text)[0], t.tabSize); i < s.to && /\s/.test(s.text[i - s.from]);) i++;
			c ? {from: r, to: i} = c : r > s.from && r < s.from + 100 && !/\S/.test(s.text.slice(0, r)) && (r = s.from);
			let d = ["", Ae(t, u)];
			return c && d.push(Ae(t, l.lineIndent(s.from, -1))), {
				changes: {
					from: r,
					to: i,
					insert: o.of(d)
				},
				range: a.cursor(r + 1 + d[1].length)
			};
		});
		return n(t.update(r, {
			scrollIntoView: !0,
			userEvent: "input"
		})), !0;
	};
}
function Mi(e, t) {
	let n = -1;
	return e.changeByRange((r) => {
		let i = [];
		for (let a = r.from; a <= r.to;) {
			let o = e.doc.lineAt(a);
			o.number > n && (r.empty || r.to > o.from) && (t(o, i, r), n = o.number), a = o.to + 1;
		}
		let o = e.changes(i);
		return {
			changes: i,
			range: a.range(o.mapPos(r.anchor, 1), o.mapPos(r.head, 1))
		};
	});
}
var Ni = ({ state: e, dispatch: t }) => {
	if (e.readOnly) return !1;
	let n = Object.create(null), r = new Fe(e, { overrideIndentation: (e) => n[e] ?? -1 }), i = Mi(e, (t, i, a) => {
		let o = ke(r, t.from);
		if (o == null) return;
		/\S/.test(t.text) || (o = 0);
		let s = /^\s*/.exec(t.text)[0], c = Ae(e, o);
		(s != c || a.from < t.from + s.length) && (n[t.from] = o, i.push({
			from: t.from,
			to: t.from + s.length,
			insert: c
		}));
	});
	return i.changes.empty || t(e.update(i, { userEvent: "indent" })), !0;
}, Pi = ({ state: e, dispatch: t }) => !e.readOnly && (t(e.update(Mi(e, (t, n) => {
	n.push({
		from: t.from,
		insert: e.facet(De)
	});
}), { userEvent: "input.indent" })), !0), Fi = ({ state: e, dispatch: t }) => !e.readOnly && (t(e.update(Mi(e, (t, n) => {
	let r = /^\s*/.exec(t.text)[0];
	if (!r) return;
	let i = ae(r, e.tabSize), a = 0, o = Ae(e, Math.max(0, i - Be(e)));
	for (; a < r.length && a < o.length && r.charCodeAt(a) == o.charCodeAt(a);) a++;
	n.push({
		from: t.from + a,
		to: t.from + r.length,
		insert: o.slice(a)
	});
}), { userEvent: "delete.dedent" })), !0), Ii = (e) => (e.setTabFocusMode(), !0), Li = [
	{
		key: "Ctrl-b",
		run: or,
		shift: jr,
		preventDefault: !0
	},
	{
		key: "Ctrl-f",
		run: sr,
		shift: Mr
	},
	{
		key: "Ctrl-p",
		run: gr,
		shift: zr
	},
	{
		key: "Ctrl-n",
		run: _r,
		shift: Br
	},
	{
		key: "Ctrl-a",
		run: Er,
		shift: Jr
	},
	{
		key: "Ctrl-e",
		run: Dr,
		shift: Yr
	},
	{
		key: "Ctrl-d",
		run: di
	},
	{
		key: "Ctrl-h",
		run: ui
	},
	{
		key: "Ctrl-k",
		run: hi
	},
	{
		key: "Ctrl-Alt-h",
		run: pi
	},
	{
		key: "Ctrl-o",
		run: vi
	},
	{
		key: "Ctrl-t",
		run: yi
	},
	{
		key: "Ctrl-v",
		run: xr
	}
], Ri = /*@__PURE__*/ [
	{
		key: "ArrowLeft",
		run: or,
		shift: jr,
		preventDefault: !0
	},
	{
		key: "Mod-ArrowLeft",
		mac: "Alt-ArrowLeft",
		run: lr,
		shift: Pr,
		preventDefault: !0
	},
	{
		mac: "Cmd-ArrowLeft",
		run: wr,
		shift: Kr,
		preventDefault: !0
	},
	{
		key: "ArrowRight",
		run: sr,
		shift: Mr,
		preventDefault: !0
	},
	{
		key: "Mod-ArrowRight",
		mac: "Alt-ArrowRight",
		run: ur,
		shift: Fr,
		preventDefault: !0
	},
	{
		mac: "Cmd-ArrowRight",
		run: Tr,
		shift: qr,
		preventDefault: !0
	},
	{
		key: "ArrowUp",
		run: gr,
		shift: zr,
		preventDefault: !0
	},
	{
		mac: "Cmd-ArrowUp",
		run: Xr,
		shift: Qr
	},
	{
		mac: "Ctrl-ArrowUp",
		run: br,
		shift: Hr
	},
	{
		key: "ArrowDown",
		run: _r,
		shift: Br,
		preventDefault: !0
	},
	{
		mac: "Cmd-ArrowDown",
		run: Zr,
		shift: $r
	},
	{
		mac: "Ctrl-ArrowDown",
		run: xr,
		shift: Ur
	},
	{
		key: "PageUp",
		run: br,
		shift: Hr
	},
	{
		key: "PageDown",
		run: xr,
		shift: Ur
	},
	{
		key: "Home",
		run: Cr,
		shift: Gr,
		preventDefault: !0
	},
	{
		key: "Mod-Home",
		run: Xr,
		shift: Qr
	},
	{
		key: "End",
		run: Sr,
		shift: Wr,
		preventDefault: !0
	},
	{
		key: "Mod-End",
		run: Zr,
		shift: $r
	},
	{
		key: "Enter",
		run: ki,
		shift: ki
	},
	{
		key: "Mod-a",
		run: ei
	},
	{
		key: "Backspace",
		run: ui,
		shift: ui,
		preventDefault: !0
	},
	{
		key: "Delete",
		run: di,
		preventDefault: !0
	},
	{
		key: "Mod-Backspace",
		mac: "Alt-Backspace",
		run: pi,
		preventDefault: !0
	},
	{
		key: "Mod-Delete",
		mac: "Alt-Delete",
		run: mi,
		preventDefault: !0
	},
	{
		mac: "Mod-Backspace",
		run: gi,
		preventDefault: !0
	},
	{
		mac: "Mod-Delete",
		run: _i,
		preventDefault: !0
	}
].concat(/*@__PURE__*/ Li.map((e) => ({
	mac: e.key,
	run: e.run,
	shift: e.shift
}))), zi = /*@__PURE__*/ [
	{
		key: "Alt-ArrowLeft",
		mac: "Ctrl-ArrowLeft",
		run: pr,
		shift: Ir
	},
	{
		key: "Alt-ArrowRight",
		mac: "Ctrl-ArrowRight",
		run: mr,
		shift: Lr
	},
	{
		key: "Alt-ArrowUp",
		run: Si
	},
	{
		key: "Shift-Alt-ArrowUp",
		run: Ti
	},
	{
		key: "Alt-ArrowDown",
		run: Ci
	},
	{
		key: "Shift-Alt-ArrowDown",
		run: Ei
	},
	{
		key: "Mod-Alt-ArrowUp",
		run: ii
	},
	{
		key: "Mod-Alt-ArrowDown",
		run: ai
	},
	{
		key: "Escape",
		run: oi
	},
	{
		key: "Mod-Enter",
		run: Ai
	},
	{
		key: "Alt-l",
		mac: "Ctrl-l",
		run: ti
	},
	{
		key: "Mod-i",
		run: ni,
		preventDefault: !0
	},
	{
		key: "Mod-[",
		run: Fi
	},
	{
		key: "Mod-]",
		run: Pi
	},
	{
		key: "Mod-Alt-\\",
		run: Ni
	},
	{
		key: "Shift-Mod-k",
		run: Di
	},
	{
		key: "Shift-Mod-\\",
		run: kr
	},
	{
		key: "Mod-/",
		run: wn
	},
	{
		key: "Alt-A",
		mac: "Ctrl-A",
		run: Dn
	},
	{
		key: "Ctrl-m",
		mac: "Shift-Alt-m",
		run: Ii
	}
].concat(Ri), Bi = {
	key: "Tab",
	run: Pi,
	shift: Fi
}, Vi = typeof String.prototype.normalize == "function" ? (e) => e.normalize("NFKD") : (e) => e, U = class {
	constructor(e, t, n = 0, r = e.length, i, a) {
		this.test = a, this.value = {
			from: 0,
			to: 0,
			precise: !1
		}, this.done = !1, this.matches = [], this.buffer = "", this.bufferPos = 0, this.iter = e.iterRange(n, r), this.bufferStart = n, this.normalize = i ? (e) => i(Vi(e)) : Vi, this.query = this.normalize(t);
	}
	peek() {
		if (this.bufferPos == this.buffer.length) {
			if (this.bufferStart += this.buffer.length, this.iter.next(), this.iter.done) return -1;
			this.bufferPos = 0, this.buffer = this.iter.value;
		}
		return S(this.buffer, this.bufferPos);
	}
	next() {
		for (; this.matches.length;) this.matches.pop();
		return this.nextOverlapping();
	}
	nextOverlapping() {
		for (;;) {
			let e = this.peek();
			if (e < 0) return this.done = !0, this;
			let t = ee(e), n = this.bufferStart + this.bufferPos;
			this.bufferPos += l(e);
			let r = this.normalize(t);
			if (r.length) for (let e = 0, i = n, a = !0;; e++) {
				let n = r.charCodeAt(e), o = this.match(n, i, a, this.bufferPos + this.bufferStart, e == r.length - 1);
				if (o) return this.value = o, this;
				if (e == r.length - 1) break;
				a && e < t.length && t.charCodeAt(e) == n ? i++ : a = !1;
			}
		}
	}
	match(e, t, n, r, i) {
		let a = null;
		for (let t = 0; t < this.matches.length;) {
			let n = this.matches[t], o = !1;
			this.query.charCodeAt(n.index) == e && (n.index == this.query.length - 1 ? a = {
				from: n.from,
				to: r,
				precise: i && n.precise
			} : (n.index++, o = !0)), o ? t++ : this.matches.splice(t, 1);
		}
		return this.query.charCodeAt(0) == e && (this.query.length == 1 ? a = {
			from: t,
			to: r,
			precise: n && i
		} : this.matches.push({
			from: t,
			index: 1,
			precise: n
		})), a && this.test && !this.test(a.from, a.to, this.buffer, this.bufferStart) && (a = null), a;
	}
};
typeof Symbol < "u" && (U.prototype[Symbol.iterator] = function() {
	return this;
});
var Hi = {
	from: -1,
	to: -1,
	match: /*@__PURE__*/ /.*/.exec(""),
	precise: !0
}, Ui = "gm" + (/x/.unicode == null ? "" : "u"), Wi = class {
	constructor(e, t, n, r = 0, i = e.length) {
		if (this.text = e, this.to = i, this.curLine = "", this.done = !1, this.value = Hi, /\\[sWDnr]|\n|\r|\[\^/.test(t)) return new qi(e, t, n, r, i);
		this.re = new RegExp(t, Ui + (n?.ignoreCase ? "i" : "")), this.test = n?.test, this.iter = e.iter();
		let a = e.lineAt(r);
		this.curLineStart = a.from, this.matchPos = Yi(e, r), this.getLine(this.curLineStart);
	}
	getLine(e) {
		this.iter.next(e), this.iter.lineBreak ? this.curLine = "" : (this.curLine = this.iter.value, this.curLineStart + this.curLine.length > this.to && (this.curLine = this.curLine.slice(0, this.to - this.curLineStart)), this.iter.next());
	}
	nextLine() {
		this.curLineStart = this.curLineStart + this.curLine.length + 1, this.curLineStart > this.to ? this.curLine = "" : this.getLine(0);
	}
	next() {
		for (let e = this.matchPos - this.curLineStart;;) {
			this.re.lastIndex = e;
			let t = this.matchPos <= this.to && this.re.exec(this.curLine);
			if (t) {
				let n = this.curLineStart + t.index, r = n + t[0].length;
				if (this.matchPos = Yi(this.text, r + +(n == r)), n == this.curLineStart + this.curLine.length && this.nextLine(), (n < r || n > this.value.to) && (!this.test || this.test(n, r, t))) return this.value = {
					from: n,
					to: r,
					precise: !0,
					match: t
				}, this;
				e = this.matchPos - this.curLineStart;
			} else if (this.curLineStart + this.curLine.length < this.to) this.nextLine(), e = 0;
			else return this.done = !0, this;
		}
	}
}, Gi = /*@__PURE__*/ new WeakMap(), Ki = class e {
	constructor(e, t) {
		this.from = e, this.text = t;
	}
	get to() {
		return this.from + this.text.length;
	}
	static get(t, n, r) {
		let i = Gi.get(t);
		if (!i || i.from >= r || i.to <= n) {
			let i = new e(n, t.sliceString(n, r));
			return Gi.set(t, i), i;
		}
		if (i.from == n && i.to == r) return i;
		let { text: a, from: o } = i;
		return o > n && (a = t.sliceString(n, o) + a, o = n), i.to < r && (a += t.sliceString(i.to, r)), Gi.set(t, new e(o, a)), new e(n, a.slice(n - o, r - o));
	}
}, qi = class {
	constructor(e, t, n, r, i) {
		this.text = e, this.to = i, this.done = !1, this.value = Hi, this.matchPos = Yi(e, r), this.re = new RegExp(t, Ui + (n?.ignoreCase ? "i" : "")), this.test = n?.test, this.flat = Ki.get(e, r, this.chunkEnd(r + 5e3));
	}
	chunkEnd(e) {
		return e >= this.to ? this.to : this.text.lineAt(e).to;
	}
	next() {
		for (;;) {
			let e = this.re.lastIndex = this.matchPos - this.flat.from, t = this.re.exec(this.flat.text);
			if (t && !t[0] && t.index == e && (this.re.lastIndex = e + 1, t = this.re.exec(this.flat.text)), t) {
				let e = this.flat.from + t.index, n = e + t[0].length;
				if ((this.flat.to >= this.to || t.index + t[0].length <= this.flat.text.length - 10) && (!this.test || this.test(e, n, t))) return this.value = {
					from: e,
					to: n,
					precise: !0,
					match: t
				}, this.matchPos = Yi(this.text, n + +(e == n)), this;
			}
			if (this.flat.to == this.to) return this.done = !0, this;
			this.flat = Ki.get(this.text, this.flat.from, this.chunkEnd(this.flat.from + this.flat.text.length * 2));
		}
	}
};
typeof Symbol < "u" && (Wi.prototype[Symbol.iterator] = qi.prototype[Symbol.iterator] = function() {
	return this;
});
function Ji(e) {
	try {
		return new RegExp(e, Ui), !0;
	} catch {
		return !1;
	}
}
function Yi(e, t) {
	if (t >= e.length) return t;
	let n = e.lineAt(t), r;
	for (; t < n.to && (r = n.text.charCodeAt(t - n.from)) >= 56320 && r < 57344;) t++;
	return t;
}
var Xi = (e) => {
	let t = _e(e, "cm-goto-line");
	if (t) {
		let e = t.dom.querySelector("input[type=text]");
		return e && e.select(), !0;
	}
	let { state: n } = e, r = String(n.doc.lineAt(e.state.selection.main.head).number), { close: o, result: s } = i(e, {
		class: "cm-goto-line",
		label: n.phrase("Go to line"),
		input: {
			type: "text",
			name: "line",
			value: r
		},
		focus: !0,
		submitLabel: n.phrase("go")
	});
	return s.then((t) => {
		let r = t && /^([+-])?(\d+)?(:\d+)?(%)?$/.exec(t.elements.line.value);
		if (!r) {
			e.dispatch({ effects: o });
			return;
		}
		let i = n.doc.lineAt(n.selection.main.head), [, s, c, l, u] = r, d = l ? +l.slice(1) : 0, f = c ? +c : i.number;
		if (c && u) {
			let e = f / 100;
			s && (e = e * (s == "-" ? -1 : 1) + i.number / n.doc.lines), f = Math.round(n.doc.lines * e);
		} else c && s && (f = f * (s == "-" ? -1 : 1) + i.number);
		let p = n.doc.line(Math.max(1, Math.min(n.doc.lines, f))), m = a.cursor(p.from + Math.max(0, Math.min(d, p.length)));
		e.dispatch({
			effects: [o, b.scrollIntoView(m.from, { y: "center" })],
			selection: m
		});
	}), !0;
}, Zi = {
	highlightWordAroundCursor: !1,
	minSelectionLength: 1,
	maxMatches: 100,
	wholeWords: !1
}, Qi = /*@__PURE__*/ d.define({ combine(e) {
	return v(e, Zi, {
		highlightWordAroundCursor: (e, t) => e || t,
		minSelectionLength: Math.min,
		maxMatches: Math.min
	});
} });
function $i(e) {
	let t = [aa, ia];
	return e && t.push(Qi.of(e)), t;
}
var ea = /*@__PURE__*/ C.mark({ class: "cm-selectionMatch" }), ta = /*@__PURE__*/ C.mark({ class: "cm-selectionMatch cm-selectionMatch-main" });
function na(e, t, n, r) {
	return (n == 0 || e(t.sliceDoc(n - 1, n)) != h.Word) && (r == t.doc.length || e(t.sliceDoc(r, r + 1)) != h.Word);
}
function ra(e, t, n, r) {
	return e(t.sliceDoc(n, n + 1)) == h.Word && e(t.sliceDoc(r - 1, r)) == h.Word;
}
var ia = /*@__PURE__*/ ve.fromClass(class {
	constructor(e) {
		this.decorations = this.getDeco(e);
	}
	update(e) {
		(e.selectionSet || e.docChanged || e.viewportChanged) && (this.decorations = this.getDeco(e.view));
	}
	getDeco(e) {
		let t = e.state.facet(Qi), { state: n } = e, r = n.selection;
		if (r.ranges.length > 1) return C.none;
		let i = r.main, a, o = null;
		if (i.empty) {
			if (!t.highlightWordAroundCursor) return C.none;
			let e = n.wordAt(i.head);
			if (!e) return C.none;
			o = n.charCategorizer(i.head), a = n.sliceDoc(e.from, e.to);
		} else {
			let e = i.to - i.from;
			if (e < t.minSelectionLength || e > 200) return C.none;
			if (t.wholeWords) {
				if (a = n.sliceDoc(i.from, i.to), o = n.charCategorizer(i.head), !(na(o, n, i.from, i.to) && ra(o, n, i.from, i.to))) return C.none;
			} else if (a = n.sliceDoc(i.from, i.to), !a) return C.none;
		}
		let s = [];
		for (let r of e.visibleRanges) {
			let e = new U(n.doc, a, r.from, r.to);
			for (; !e.next().done;) {
				let { from: r, to: a } = e.value;
				if ((!o || na(o, n, r, a)) && (i.empty && r <= i.from && a >= i.to ? s.push(ta.range(r, a)) : (r >= i.to || a <= i.from) && s.push(ea.range(r, a)), s.length > t.maxMatches)) return C.none;
			}
		}
		return C.set(s);
	}
}, { decorations: (e) => e.decorations }), aa = /*@__PURE__*/ b.baseTheme({
	".cm-selectionMatch": { backgroundColor: "#99ff7780" },
	".cm-searchMatch .cm-selectionMatch": { backgroundColor: "transparent" }
}), oa = ({ state: e, dispatch: t }) => {
	let { selection: n } = e, r = a.create(n.ranges.map((t) => e.wordAt(t.head) || a.cursor(t.head)), n.mainIndex);
	return !r.eq(n) && (t(e.update({ selection: r })), !0);
};
function sa(e, t) {
	let { main: n, ranges: r } = e.selection, i = e.wordAt(n.head), a = i && i.from == n.from && i.to == n.to;
	for (let n = !1, i = new U(e.doc, t, r[r.length - 1].to);;) if (i.next(), i.done) {
		if (n) return null;
		i = new U(e.doc, t, 0, Math.max(0, r[r.length - 1].from - 1)), n = !0;
	} else {
		if (n && r.some((e) => e.from == i.value.from)) continue;
		if (a) {
			let t = e.wordAt(i.value.from);
			if (!t || t.from != i.value.from || t.to != i.value.to) continue;
		}
		return i.value;
	}
}
var ca = ({ state: e, dispatch: t }) => {
	let { ranges: n } = e.selection;
	if (n.some((e) => e.from === e.to)) return oa({
		state: e,
		dispatch: t
	});
	let r = e.sliceDoc(n[0].from, n[0].to);
	if (e.selection.ranges.some((t) => e.sliceDoc(t.from, t.to) != r)) return !1;
	let i = sa(e, r);
	return i ? (t(e.update({
		selection: e.selection.addRange(a.range(i.from, i.to), !1),
		effects: b.scrollIntoView(i.to)
	})), !0) : !1;
}, W = /*@__PURE__*/ d.define({ combine(e) {
	return v(e, {
		top: !1,
		caseSensitive: !1,
		literal: !1,
		regexp: !1,
		wholeWord: !1,
		createPanel: (e) => new za(e),
		scrollToMatch: (e) => b.scrollIntoView(e)
	});
} }), la = class {
	constructor(e) {
		this.search = e.search, this.caseSensitive = !!e.caseSensitive, this.literal = !!e.literal, this.regexp = !!e.regexp, this.replace = e.replace || "", this.valid = !!this.search && (!this.regexp || Ji(this.search)), this.unquoted = this.unquote(this.search), this.wholeWord = !!e.wholeWord, this.test = e.test;
	}
	unquote(e) {
		return this.literal ? e : e.replace(/\\([nrt\\])/g, (e, t) => t == "n" ? "\n" : t == "r" ? "\r" : t == "t" ? "	" : "\\");
	}
	eq(e) {
		return this.search == e.search && this.replace == e.replace && this.caseSensitive == e.caseSensitive && this.regexp == e.regexp && this.wholeWord == e.wholeWord && this.test == e.test;
	}
	create() {
		return this.regexp ? new va(this) : new pa(this);
	}
	getCursor(e, t = 0, n) {
		let r = e.doc ? e : c.create({ doc: e });
		return n ??= r.doc.length, this.regexp ? K(this, r, t, n) : G(this, r, t, n);
	}
}, ua = class {
	constructor(e) {
		this.spec = e;
	}
};
function da(e, t, n) {
	return (r, i, a, o) => n && !n(r, i, a, o) ? !1 : e(r >= o && i <= o + a.length ? a.slice(r - o, i - o) : t.doc.sliceString(r, i), t, r, i);
}
function G(e, t, n, r) {
	let i;
	return e.wholeWord && (i = fa(t.doc, t.charCategorizer(t.selection.main.head))), e.test && (i = da(e.test, t, i)), new U(t.doc, e.unquoted, n, r, e.caseSensitive ? void 0 : (e) => e.toLowerCase(), i);
}
function fa(e, t) {
	return (n, r, i, a) => ((a > n || a + i.length < r) && (a = Math.max(0, n - 2), i = e.sliceString(a, Math.min(e.length, r + 2))), (t(ha(i, n - a)) != h.Word || t(ga(i, n - a)) != h.Word) && (t(ga(i, r - a)) != h.Word || t(ha(i, r - a)) != h.Word));
}
var pa = class extends ua {
	constructor(e) {
		super(e);
	}
	nextMatch(e, t, n) {
		let r = G(this.spec, e, n, e.doc.length).nextOverlapping();
		if (r.done) {
			let n = Math.min(e.doc.length, t + this.spec.unquoted.length);
			r = G(this.spec, e, 0, n).nextOverlapping();
		}
		return r.done || r.value.from == t && r.value.to == n ? null : r.value;
	}
	prevMatchInRange(e, t, n) {
		for (let r = n;;) {
			let n = Math.max(t, r - 1e4 - this.spec.unquoted.length), i = G(this.spec, e, n, r), a = null;
			for (; !i.nextOverlapping().done;) a = i.value;
			if (a) return a;
			if (n == t) return null;
			r -= 1e4;
		}
	}
	prevMatch(e, t, n) {
		let r = this.prevMatchInRange(e, 0, t);
		return r ||= this.prevMatchInRange(e, Math.max(0, n - this.spec.unquoted.length), e.doc.length), r && (r.from != t || r.to != n) ? r : null;
	}
	getReplacement(e) {
		return this.spec.unquote(this.spec.replace);
	}
	matchAll(e, t) {
		let n = G(this.spec, e, 0, e.doc.length), r = [];
		for (; !n.next().done;) {
			if (r.length >= t) return null;
			r.push(n.value);
		}
		return r;
	}
	highlight(e, t, n, r) {
		let i = G(this.spec, e, Math.max(0, t - this.spec.unquoted.length), Math.min(n + this.spec.unquoted.length, e.doc.length));
		for (; !i.next().done;) r(i.value.from, i.value.to);
	}
};
function ma(e, t, n) {
	return (r, i, a) => (!n || n(r, i, a)) && e(a[0], t, r, i);
}
function K(e, t, n, r) {
	let i;
	return e.wholeWord && (i = _a(t.charCategorizer(t.selection.main.head))), e.test && (i = ma(e.test, t, i)), new Wi(t.doc, e.search, {
		ignoreCase: !e.caseSensitive,
		test: i
	}, n, r);
}
function ha(e, t) {
	return e.slice(y(e, t, !1), t);
}
function ga(e, t) {
	return e.slice(t, y(e, t));
}
function _a(e) {
	return (t, n, r) => !r[0].length || (e(ha(r.input, r.index)) != h.Word || e(ga(r.input, r.index)) != h.Word) && (e(ga(r.input, r.index + r[0].length)) != h.Word || e(ha(r.input, r.index + r[0].length)) != h.Word);
}
var va = class extends ua {
	nextMatch(e, t, n) {
		let r = K(this.spec, e, n, e.doc.length).next();
		return r.done && (r = K(this.spec, e, 0, t).next()), r.done ? null : r.value;
	}
	prevMatchInRange(e, t, n) {
		for (let r = 1;; r++) {
			let i = Math.max(t, n - r * 1e4), a = K(this.spec, e, i, n), o = null;
			for (; !a.next().done;) o = a.value;
			if (o && (i == t || o.from > i + 10)) return o;
			if (i == t) return null;
		}
	}
	prevMatch(e, t, n) {
		return this.prevMatchInRange(e, 0, t) || this.prevMatchInRange(e, n, e.doc.length);
	}
	getReplacement(e) {
		return this.spec.unquote(this.spec.replace).replace(/\$([$&]|\d+)/g, (t, n) => {
			if (n == "&") return e.match[0];
			if (n == "$") return "$";
			for (let t = n.length; t > 0; t--) {
				let r = +n.slice(0, t);
				if (r > 0 && r < e.match.length) return e.match[r] + n.slice(t);
			}
			return t;
		});
	}
	matchAll(e, t) {
		let n = K(this.spec, e, 0, e.doc.length), r = [];
		for (; !n.next().done;) {
			if (r.length >= t) return null;
			r.push(n.value);
		}
		return r;
	}
	highlight(e, t, n, r) {
		let i = K(this.spec, e, Math.max(0, t - 250), Math.min(n + 250, e.doc.length));
		for (; !i.next().done;) r(i.value.from, i.value.to);
	}
}, ya = /*@__PURE__*/ g.define(), ba = /*@__PURE__*/ g.define(), q = /*@__PURE__*/ _.define({
	create(e) {
		return new xa(Na(e).create(), null);
	},
	update(e, t) {
		for (let n of t.effects) n.is(ya) ? e = new xa(n.value.create(), e.panel) : n.is(ba) && (e = new xa(e.query, n.value ? Ma : null));
		return e;
	},
	provide: (e) => r.from(e, (e) => e.panel)
}), xa = class {
	constructor(e, t) {
		this.query = e, this.panel = t;
	}
}, Sa = /*@__PURE__*/ C.mark({ class: "cm-searchMatch" }), Ca = /*@__PURE__*/ C.mark({ class: "cm-searchMatch cm-searchMatch-selected" }), wa = /*@__PURE__*/ ve.fromClass(class {
	constructor(e) {
		this.view = e, this.decorations = this.highlight(e.state.field(q));
	}
	update(e) {
		let t = e.state.field(q);
		(t != e.startState.field(q) || e.docChanged || e.selectionSet || e.viewportChanged) && (this.decorations = this.highlight(t));
	}
	highlight({ query: e, panel: t }) {
		if (!t || !e.spec.valid) return C.none;
		let { view: n } = this, r = new ie();
		for (let t = 0, i = n.visibleRanges, a = i.length; t < a; t++) {
			let { from: o, to: s } = i[t];
			for (; t < a - 1 && s > i[t + 1].from - 500;) s = i[++t].to;
			e.highlight(n.state, o, s, (e, t) => {
				let i = n.state.selection.ranges.some((n) => n.from == e && n.to == t);
				r.add(e, t, i ? Ca : Sa);
			});
		}
		return r.finish();
	}
}, { decorations: (e) => e.decorations });
function Ta(e) {
	return (t) => {
		let n = t.state.field(q, !1);
		return n && n.query.spec.valid ? e(t, n) : Ia(t);
	};
}
var Ea = /*@__PURE__*/ Ta((e, { query: t }) => {
	let { to: n } = e.state.selection.main, r = t.nextMatch(e.state, n, n);
	if (!r) return !1;
	let i = a.single(r.from, r.to), o = e.state.facet(W);
	return e.dispatch({
		selection: i,
		effects: [Ha(e, r), o.scrollToMatch(i.main, e)],
		userEvent: "select.search"
	}), Fa(e), !0;
}), Da = /*@__PURE__*/ Ta((e, { query: t }) => {
	let { state: n } = e, { from: r } = n.selection.main, i = t.prevMatch(n, r, r);
	if (!i) return !1;
	let o = a.single(i.from, i.to), s = e.state.facet(W);
	return e.dispatch({
		selection: o,
		effects: [Ha(e, i), s.scrollToMatch(o.main, e)],
		userEvent: "select.search"
	}), Fa(e), !0;
}), Oa = /*@__PURE__*/ Ta((e, { query: t }) => {
	let n = t.matchAll(e.state, 1e3);
	return !n || !n.length ? !1 : (e.dispatch({
		selection: a.create(n.map((e) => a.range(e.from, e.to))),
		userEvent: "select.search.matches"
	}), !0);
}), ka = ({ state: e, dispatch: t }) => {
	let n = e.selection;
	if (n.ranges.length > 1 || n.main.empty) return !1;
	let { from: r, to: i } = n.main, o = [], s = 0;
	for (let t = new U(e.doc, e.sliceDoc(r, i)); !t.next().done;) {
		if (o.length > 1e3) return !1;
		t.value.from == r && (s = o.length), o.push(a.range(t.value.from, t.value.to));
	}
	return t(e.update({
		selection: a.create(o, s),
		userEvent: "select.search.matches"
	})), !0;
}, Aa = /*@__PURE__*/ Ta((e, { query: t }) => {
	let { state: n } = e, { from: r, to: i } = n.selection.main;
	if (n.readOnly) return !1;
	let o = t.nextMatch(n, r, r);
	if (!o) return !1;
	let s = o, c = [], l, u, d = [];
	s.precise ? s.from == r && s.to == i && (u = n.toText(t.getReplacement(s)), c.push({
		from: s.from,
		to: s.to,
		insert: u
	}), s = t.nextMatch(n, s.from, s.to), d.push(b.announce.of(n.phrase("replaced match on line $", n.doc.lineAt(r).number) + "."))) : s = t.nextMatch(n, s.from, s.to);
	let f = e.state.changes(c);
	return s && (l = a.single(s.from, s.to).map(f), d.push(Ha(e, s)), d.push(n.facet(W).scrollToMatch(l.main, e))), e.dispatch({
		changes: f,
		selection: l,
		effects: d,
		userEvent: "input.replace"
	}), !0;
}), ja = /*@__PURE__*/ Ta((e, { query: t }) => {
	if (e.state.readOnly) return !1;
	let n = [];
	for (let r of t.matchAll(e.state, 1e9)) {
		let { from: e, to: i, precise: a } = r;
		a && n.push({
			from: e,
			to: i,
			insert: t.getReplacement(r)
		});
	}
	if (!n.length) return !1;
	let r = e.state.phrase("replaced $ matches", n.length) + ".";
	return e.dispatch({
		changes: n,
		effects: b.announce.of(r),
		userEvent: "input.replace.all"
	}), !0;
});
function Ma(e) {
	return e.state.facet(W).createPanel(e);
}
function Na(e, t) {
	let n = e.selection.main, r = n.empty || n.to > n.from + 100 ? "" : e.sliceDoc(n.from, n.to);
	if (t && !r) return t;
	let i = e.facet(W);
	return new la({
		search: t?.literal ?? i.literal ? r : r.replace(/\n/g, "\\n"),
		caseSensitive: t?.caseSensitive ?? i.caseSensitive,
		literal: t?.literal ?? i.literal,
		regexp: t?.regexp ?? i.regexp,
		wholeWord: t?.wholeWord ?? i.wholeWord
	});
}
function Pa(e) {
	let t = he(e, Ma);
	return t && t.dom.querySelector("[main-field]");
}
function Fa(e) {
	let t = Pa(e);
	t && t == e.root.activeElement && t.select();
}
var Ia = (e) => {
	let t = e.state.field(q, !1);
	if (t && t.panel) {
		let n = Pa(e);
		if (n && n != e.root.activeElement) {
			let r = Na(e.state, t.query.spec);
			r.valid && e.dispatch({ effects: ya.of(r) }), n.focus(), n.select();
		}
	} else e.dispatch({ effects: [ba.of(!0), t ? ya.of(Na(e.state, t.query.spec)) : g.appendConfig.of(Wa)] });
	return !0;
}, La = (e) => {
	let t = e.state.field(q, !1);
	if (!t || !t.panel) return !1;
	let n = he(e, Ma);
	return n && n.dom.contains(e.root.activeElement) && e.focus(), e.dispatch({ effects: ba.of(!1) }), !0;
}, Ra = [
	{
		key: "Mod-f",
		run: Ia,
		scope: "editor search-panel"
	},
	{
		key: "F3",
		run: Ea,
		shift: Da,
		scope: "editor search-panel",
		preventDefault: !0
	},
	{
		key: "Mod-g",
		run: Ea,
		shift: Da,
		scope: "editor search-panel",
		preventDefault: !0
	},
	{
		key: "Escape",
		run: La,
		scope: "editor search-panel"
	},
	{
		key: "Mod-Shift-l",
		run: ka
	},
	{
		key: "Mod-Alt-g",
		run: Xi
	},
	{
		key: "Mod-d",
		run: ca,
		preventDefault: !0
	}
], za = class {
	constructor(e) {
		this.view = e;
		let t = this.query = e.state.field(q).query.spec;
		this.commit = this.commit.bind(this), this.searchField = x("input", {
			value: t.search,
			placeholder: J(e, "Find"),
			"aria-label": J(e, "Find"),
			class: "cm-textfield",
			name: "search",
			form: "",
			"main-field": "true",
			onchange: this.commit,
			onkeyup: this.commit
		}), this.replaceField = x("input", {
			value: t.replace,
			placeholder: J(e, "Replace"),
			"aria-label": J(e, "Replace"),
			class: "cm-textfield",
			name: "replace",
			form: "",
			onchange: this.commit,
			onkeyup: this.commit
		}), this.caseField = x("input", {
			type: "checkbox",
			name: "case",
			form: "",
			checked: t.caseSensitive,
			onchange: this.commit
		}), this.reField = x("input", {
			type: "checkbox",
			name: "re",
			form: "",
			checked: t.regexp,
			onchange: this.commit
		}), this.wordField = x("input", {
			type: "checkbox",
			name: "word",
			form: "",
			checked: t.wholeWord,
			onchange: this.commit
		});
		function n(e, t, n) {
			return x("button", {
				class: "cm-button",
				name: e,
				onclick: t,
				type: "button"
			}, n);
		}
		this.dom = x("div", {
			onkeydown: (e) => this.keydown(e),
			class: "cm-search"
		}, [
			this.searchField,
			n("next", () => Ea(e), [J(e, "next")]),
			n("prev", () => Da(e), [J(e, "previous")]),
			n("select", () => Oa(e), [J(e, "all")]),
			x("label", null, [this.caseField, J(e, "match case")]),
			x("label", null, [this.reField, J(e, "regexp")]),
			x("label", null, [this.wordField, J(e, "by word")]),
			...e.state.readOnly ? [] : [
				x("br"),
				this.replaceField,
				n("replace", () => Aa(e), [J(e, "replace")]),
				n("replaceAll", () => ja(e), [J(e, "replace all")])
			],
			x("button", {
				name: "close",
				onclick: () => La(e),
				"aria-label": J(e, "close"),
				type: "button"
			}, ["×"])
		]);
	}
	commit() {
		let e = new la({
			search: this.searchField.value,
			caseSensitive: this.caseField.checked,
			regexp: this.reField.checked,
			wholeWord: this.wordField.checked,
			replace: this.replaceField.value
		});
		e.eq(this.query) || (this.query = e, this.view.dispatch({ effects: ya.of(e) }));
	}
	keydown(e) {
		re(this.view, e, "search-panel") ? e.preventDefault() : e.keyCode == 13 && e.target == this.searchField ? (e.preventDefault(), (e.shiftKey ? Da : Ea)(this.view)) : e.keyCode == 13 && e.target == this.replaceField && (e.preventDefault(), Aa(this.view));
	}
	update(e) {
		for (let t of e.transactions) for (let e of t.effects) e.is(ya) && !e.value.eq(this.query) && this.setQuery(e.value);
	}
	setQuery(e) {
		this.query = e, this.searchField.value = e.search, this.replaceField.value = e.replace, this.caseField.checked = e.caseSensitive, this.reField.checked = e.regexp, this.wordField.checked = e.wholeWord;
	}
	mount() {
		this.searchField.select();
	}
	get pos() {
		return 80;
	}
	get top() {
		return this.view.state.facet(W).top;
	}
};
function J(e, t) {
	return e.state.phrase(t);
}
var Ba = 30, Va = /[\s\.,:;?!]/;
function Ha(e, { from: t, to: n }) {
	let r = e.state.doc.lineAt(t), i = e.state.doc.lineAt(n).to, a = Math.max(r.from, t - Ba), o = Math.min(i, n + Ba), s = e.state.sliceDoc(a, o);
	if (a != r.from) {
		for (let e = 0; e < Ba; e++) if (!Va.test(s[e + 1]) && Va.test(s[e])) {
			s = s.slice(e);
			break;
		}
	}
	if (o != i) {
		for (let e = s.length - 1; e > s.length - Ba; e--) if (!Va.test(s[e - 1]) && Va.test(s[e])) {
			s = s.slice(0, e);
			break;
		}
	}
	return b.announce.of(`${e.state.phrase("current match")}. ${s} ${e.state.phrase("on line")} ${r.number}.`);
}
var Ua = /*@__PURE__*/ b.baseTheme({
	".cm-panel.cm-search": {
		padding: "2px 6px 4px",
		position: "relative",
		"& [name=close]": {
			position: "absolute",
			top: "0",
			right: "4px",
			backgroundColor: "inherit",
			border: "none",
			font: "inherit",
			padding: 0,
			margin: 0
		},
		"& input, & button, & label": { margin: ".2em .6em .2em 0" },
		"& input[type=checkbox]": { marginRight: ".2em" },
		"& label": {
			fontSize: "80%",
			whiteSpace: "pre"
		}
	},
	"&light .cm-searchMatch": { backgroundColor: "#ffff0054" },
	"&dark .cm-searchMatch": { backgroundColor: "#00ffff8a" },
	"&light .cm-searchMatch-selected": { backgroundColor: "#ff6a0054" },
	"&dark .cm-searchMatch-selected": { backgroundColor: "#ff00ff8a" }
}), Wa = [
	q,
	/*@__PURE__*/ Te.low(wa),
	Ua
], Ga = class {
	constructor(e, t, n) {
		this.from = e, this.to = t, this.diagnostic = n;
	}
}, Ka = class e {
	constructor(e, t, n) {
		this.diagnostics = e, this.panel = t, this.selected = n;
	}
	static init(t, n, r) {
		let i = r.facet(io).markerFilter;
		i && (t = i(t, r));
		let a = t.slice().sort((e, t) => e.from - t.from || e.to - t.to), o = new ie(), s = [], c = 0, l = r.doc.iter(), u = 0, d = r.doc.length;
		for (let e = 0;;) {
			let t = e == a.length ? null : a[e];
			if (!t && !s.length) break;
			let n, r;
			if (s.length) n = c, r = s.reduce((e, t) => Math.min(e, t.to), t && t.from > n ? t.from : 1e8);
			else {
				if (n = t.from, n > d) break;
				r = t.to, s.push(t), e++;
			}
			for (; e < a.length;) {
				let t = a[e];
				if (t.from == n && (t.to > t.from || t.to == n)) s.push(t), e++, r = Math.min(t.to, r);
				else {
					r = Math.min(t.from, r);
					break;
				}
			}
			r = Math.min(r, d);
			let i = !1;
			if (s.some((e) => e.from == n && (e.to == r || r == d)) && (i = n == r, !i && r - n < 10)) {
				let e = n - (u + l.value.length);
				e > 0 && (l.next(e), u = n);
				for (let e = n;;) {
					if (e >= r) {
						i = !0;
						break;
					}
					if (!l.lineBreak && u + l.value.length > e) break;
					e = u + l.value.length, u += l.value.length, l.next();
				}
			}
			let f = go(s);
			if (i) o.add(n, n, C.widget({
				widget: new co(f),
				diagnostics: s.slice()
			}));
			else {
				let e = s.reduce((e, t) => t.markClass ? e + " " + t.markClass : e, "");
				o.add(n, r, C.mark({
					class: "cm-lintRange cm-lintRange-" + f + e,
					diagnostics: s.slice(),
					inclusiveEnd: s.some((e) => e.to > r)
				}));
			}
			if (c = r, c == d) break;
			for (let e = 0; e < s.length; e++) s[e].to <= c && s.splice(e--, 1);
		}
		let f = o.finish();
		return new e(f, n, Y(f));
	}
};
function Y(e, t = null, n = 0) {
	let r = null;
	return e.between(n, 1e9, (e, n, { spec: i }) => {
		if (!(t && i.diagnostics.indexOf(t) < 0)) {
			if (!r) r = new Ga(e, n, t || i.diagnostics[0]);
			else if (i.diagnostics.indexOf(r.diagnostic) < 0) return !1;
			else r = new Ga(r.from, n, r.diagnostic);
		}
	}), r;
}
function qa(e, t) {
	let n = t.pos, r = t.end || n, i = e.state.facet(io).hideOn(e, n, r);
	if (i != null) return i;
	let a = e.startState.doc.lineAt(t.pos);
	return !!(e.effects.some((e) => e.is(Ya)) || e.changes.touchesRange(a.from, Math.max(a.to, r)));
}
function Ja(e, t) {
	return e.field(X, !1) ? t : t.concat(g.appendConfig.of(vo));
}
var Ya = /*@__PURE__*/ g.define(), Xa = /*@__PURE__*/ g.define(), Za = /*@__PURE__*/ g.define(), X = /*@__PURE__*/ _.define({
	create() {
		return new Ka(C.none, null, null);
	},
	update(e, t) {
		if (t.docChanged && e.diagnostics.size) {
			let n = e.diagnostics.map(t.changes), r = null, i = e.panel;
			if (e.selected) {
				let i = t.changes.mapPos(e.selected.from, 1);
				r = Y(n, e.selected.diagnostic, i) || Y(n, null, i);
			}
			!n.size && i && t.state.facet(io).autoPanel && (i = null), e = new Ka(n, i, r);
		}
		for (let n of t.effects) if (n.is(Ya)) {
			let r = t.state.facet(io).autoPanel ? n.value.length ? uo.open : null : e.panel;
			e = Ka.init(n.value, r, t.state);
		} else n.is(Xa) ? e = new Ka(e.diagnostics, n.value ? uo.open : null, e.selected) : n.is(Za) && (e = new Ka(e.diagnostics, e.panel, n.value));
		return e;
	},
	provide: (e) => [r.from(e, (e) => e.panel), b.decorations.from(e, (e) => e.diagnostics)]
}), Qa = /*@__PURE__*/ C.mark({ class: "cm-lintRange cm-lintRange-active" });
function $a(e, t, n) {
	let { diagnostics: r } = e.state.field(X), i, a = -1, o = -1;
	r.between(t - +(n < 0), t + +(n > 0), (e, r, { spec: s }) => {
		if (t >= e && t <= r && (e == r || (t > e || n > 0) && (t < r || n < 0))) return i = s.diagnostics, a = e, o = r, !1;
	});
	let s = e.state.facet(io).tooltipFilter;
	return i && s && (i = s(i, e.state)), i ? {
		pos: a,
		end: o,
		above: !0,
		create() {
			return { dom: eo(e, i) };
		}
	} : null;
}
function eo(e, t) {
	return x("ul", { class: "cm-tooltip-lint" }, t.map((t) => so(e, t, !1)));
}
var to = (e) => {
	let t = e.state.field(X, !1);
	t && t.panel || e.dispatch({ effects: Ja(e.state, [Xa.of(!0)]) });
	let n = he(e, uo.open);
	return n && n.dom.querySelector(".cm-panel-lint ul").focus(), !0;
}, no = (e) => {
	let t = e.state.field(X, !1);
	return !t || !t.panel ? !1 : (e.dispatch({ effects: Xa.of(!1) }), !0);
}, ro = [{
	key: "Mod-Shift-m",
	run: to,
	preventDefault: !0
}, {
	key: "F8",
	run: (e) => {
		let t = e.state.field(X, !1);
		if (!t) return !1;
		let n = e.state.selection.main, r = Y(t.diagnostics, null, n.to + 1);
		return !r && (r = Y(t.diagnostics, null, 0), !r || r.from == n.from && r.to == n.to) ? !1 : (e.dispatch({
			selection: {
				anchor: r.from,
				head: r.to
			},
			scrollIntoView: !0
		}), me(e, r.from, 1, {
			tooltip: _o,
			until: (e) => e.docChanged || e.newSelection.main.head < r.from || e.newSelection.main.head > r.to
		}), !0);
	}
}], io = /*@__PURE__*/ d.define({ combine(e) {
	return {
		sources: e.map((e) => e.source).filter((e) => e != null),
		...v(e.map((e) => e.config), {
			delay: 750,
			markerFilter: null,
			tooltipFilter: null,
			needsRefresh: null,
			hideOn: () => null
		}, {
			delay: Math.max,
			markerFilter: ao,
			tooltipFilter: ao,
			needsRefresh: (e, t) => e ? t ? (n) => e(n) || t(n) : e : t,
			hideOn: (e, t) => e ? t ? (n, r, i) => e(n, r, i) || t(n, r, i) : e : t,
			autoPanel: (e, t) => e || t
		})
	};
} });
function ao(e, t) {
	return e ? t ? (n, r) => t(e(n, r), r) : e : t;
}
function oo(e) {
	let t = [];
	if (e) actions: for (let { name: n } of e) {
		for (let e = 0; e < n.length; e++) {
			let r = n[e];
			if (/[a-zA-Z]/.test(r) && !t.some((e) => e.toLowerCase() == r.toLowerCase())) {
				t.push(r);
				continue actions;
			}
		}
		t.push("");
	}
	return t;
}
function so(e, t, n) {
	let r = n ? oo(t.actions) : [];
	return x("li", { class: "cm-diagnostic cm-diagnostic-" + t.severity }, x("span", { class: "cm-diagnosticText" }, t.renderMessage ? t.renderMessage(e) : t.message), t.actions?.map((n, i) => {
		let a = !1, o = (r) => {
			if (r.preventDefault(), a) return;
			a = !0;
			let i = Y(e.state.field(X).diagnostics, t);
			i && n.apply(e, i.from, i.to);
		}, { name: s } = n, c = r[i] ? s.indexOf(r[i]) : -1, l = c < 0 ? s : [
			s.slice(0, c),
			x("u", s.slice(c, c + 1)),
			s.slice(c + 1)
		], u = n.markClass ? " " + n.markClass : "";
		return x("button", {
			type: "button",
			class: "cm-diagnosticAction" + u,
			onclick: o,
			onmousedown: o,
			"aria-label": ` Action: ${s}${c < 0 ? "" : ` (access key "${r[i]})"`}.`
		}, l);
	}), t.source && x("div", { class: "cm-diagnosticSource" }, t.source));
}
var co = class extends ce {
	constructor(e) {
		super(), this.sev = e;
	}
	eq(e) {
		return e.sev == this.sev;
	}
	toDOM() {
		return x("span", { class: "cm-lintPoint cm-lintPoint-" + this.sev });
	}
}, lo = class {
	constructor(e, t) {
		this.diagnostic = t, this.id = "item_" + Math.floor(Math.random() * 4294967295).toString(16), this.dom = so(e, t, !0), this.dom.id = this.id, this.dom.setAttribute("role", "option");
	}
}, uo = class e {
	constructor(e) {
		this.view = e, this.items = [];
		let t = (t) => {
			if (!(t.ctrlKey || t.altKey || t.metaKey)) {
				if (t.keyCode == 27) no(this.view), this.view.focus();
				else if (t.keyCode == 38 || t.keyCode == 33) this.moveSelection((this.selectedIndex - 1 + this.items.length) % this.items.length);
				else if (t.keyCode == 40 || t.keyCode == 34) this.moveSelection((this.selectedIndex + 1) % this.items.length);
				else if (t.keyCode == 36) this.moveSelection(0);
				else if (t.keyCode == 35) this.moveSelection(this.items.length - 1);
				else if (t.keyCode == 13) this.view.focus();
				else if (t.keyCode >= 65 && t.keyCode <= 90 && this.selectedIndex >= 0) {
					let { diagnostic: n } = this.items[this.selectedIndex], r = oo(n.actions);
					for (let i = 0; i < r.length; i++) if (r[i].toUpperCase().charCodeAt(0) == t.keyCode) {
						let t = Y(this.view.state.field(X).diagnostics, n);
						t && n.actions[i].apply(e, t.from, t.to);
					}
				} else return;
				t.preventDefault();
			}
		}, n = (e) => {
			for (let t = 0; t < this.items.length; t++) this.items[t].dom.contains(e.target) && this.moveSelection(t);
		};
		this.list = x("ul", {
			tabIndex: 0,
			role: "listbox",
			"aria-label": this.view.state.phrase("Diagnostics"),
			onkeydown: t,
			onclick: n
		}), this.dom = x("div", { class: "cm-panel-lint" }, this.list, x("button", {
			type: "button",
			name: "close",
			"aria-label": this.view.state.phrase("close"),
			onclick: () => no(this.view)
		}, "×")), this.update();
	}
	get selectedIndex() {
		let e = this.view.state.field(X).selected;
		if (!e) return -1;
		for (let t = 0; t < this.items.length; t++) if (this.items[t].diagnostic == e.diagnostic) return t;
		return -1;
	}
	update() {
		let { diagnostics: e, selected: t } = this.view.state.field(X), n = 0, r = !1, i = null, a = /* @__PURE__ */ new Set();
		for (e.between(0, this.view.state.doc.length, (e, o, { spec: s }) => {
			for (let e of s.diagnostics) {
				if (a.has(e)) continue;
				a.add(e);
				let o = -1, s;
				for (let t = n; t < this.items.length; t++) if (this.items[t].diagnostic == e) {
					o = t;
					break;
				}
				o < 0 ? (s = new lo(this.view, e), this.items.splice(n, 0, s), r = !0) : (s = this.items[o], o > n && (this.items.splice(n, o - n), r = !0)), t && s.diagnostic == t.diagnostic ? s.dom.hasAttribute("aria-selected") || (s.dom.setAttribute("aria-selected", "true"), i = s) : s.dom.hasAttribute("aria-selected") && s.dom.removeAttribute("aria-selected"), n++;
			}
		}); n < this.items.length && !(this.items.length == 1 && this.items[0].diagnostic.from < 0);) r = !0, this.items.pop();
		this.items.length == 0 && (this.items.push(new lo(this.view, {
			from: -1,
			to: -1,
			severity: "info",
			message: this.view.state.phrase("No diagnostics")
		})), r = !0), i ? (this.list.setAttribute("aria-activedescendant", i.id), this.view.requestMeasure({
			key: this,
			read: () => ({
				sel: i.dom.getBoundingClientRect(),
				panel: this.list.getBoundingClientRect()
			}),
			write: ({ sel: e, panel: t }) => {
				let n = t.height / this.list.offsetHeight;
				e.top < t.top ? this.list.scrollTop -= (t.top - e.top) / n : e.bottom > t.bottom && (this.list.scrollTop += (e.bottom - t.bottom) / n);
			}
		})) : this.selectedIndex < 0 && this.list.removeAttribute("aria-activedescendant"), r && this.sync();
	}
	sync() {
		let e = this.list.firstChild;
		function t() {
			let t = e;
			e = t.nextSibling, t.remove();
		}
		for (let n of this.items) if (n.dom.parentNode == this.list) {
			for (; e != n.dom;) t();
			e = n.dom.nextSibling;
		} else this.list.insertBefore(n.dom, e);
		for (; e;) t();
	}
	moveSelection(e) {
		if (this.selectedIndex < 0) return;
		let t = Y(this.view.state.field(X).diagnostics, this.items[e].diagnostic);
		t && this.view.dispatch({
			selection: {
				anchor: t.from,
				head: t.to
			},
			scrollIntoView: !0,
			effects: Za.of(t)
		});
	}
	static open(t) {
		return new e(t);
	}
};
function fo(e, t = "viewBox=\"0 0 40 40\"") {
	return `url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" ${t}>${encodeURIComponent(e)}</svg>')`;
}
function po(e) {
	return fo(`<path d="m0 2.5 l2 -1.5 l1 0 l2 1.5 l1 0" stroke="${e}" fill="none" stroke-width=".7"/>`, "width=\"6\" height=\"3\"");
}
var mo = /*@__PURE__*/ b.baseTheme({
	".cm-diagnostic": {
		padding: "3px 6px 3px 8px",
		marginLeft: "-1px",
		display: "block",
		whiteSpace: "pre-wrap"
	},
	".cm-diagnostic-error": { borderLeft: "5px solid #d11" },
	".cm-diagnostic-warning": { borderLeft: "5px solid orange" },
	".cm-diagnostic-info": { borderLeft: "5px solid #999" },
	".cm-diagnostic-hint": { borderLeft: "5px solid #66d" },
	".cm-diagnosticAction": {
		font: "inherit",
		border: "none",
		padding: "2px 4px",
		backgroundColor: "#444",
		color: "white",
		borderRadius: "3px",
		marginLeft: "8px",
		cursor: "pointer"
	},
	".cm-diagnosticSource": {
		fontSize: "70%",
		opacity: .7
	},
	".cm-lintRange": {
		backgroundPosition: "left bottom",
		backgroundRepeat: "repeat-x",
		paddingBottom: "0.7px"
	},
	".cm-lintRange-error": { backgroundImage: /*@__PURE__*/ po("#f11") },
	".cm-lintRange-warning": { backgroundImage: /*@__PURE__*/ po("orange") },
	".cm-lintRange-info": { backgroundImage: /*@__PURE__*/ po("#999") },
	".cm-lintRange-hint": { backgroundImage: /*@__PURE__*/ po("#66d") },
	".cm-lintRange-active": { backgroundColor: "#ffdd9980" },
	".cm-tooltip-lint": {
		padding: 0,
		margin: 0
	},
	".cm-lintPoint": {
		position: "relative",
		"&:after": {
			content: "\"\"",
			position: "absolute",
			bottom: 0,
			left: "-2px",
			borderLeft: "3px solid transparent",
			borderRight: "3px solid transparent",
			borderBottom: "4px solid #d11"
		}
	},
	".cm-lintPoint-warning": { "&:after": { borderBottomColor: "orange" } },
	".cm-lintPoint-info": { "&:after": { borderBottomColor: "#999" } },
	".cm-lintPoint-hint": { "&:after": { borderBottomColor: "#66d" } },
	".cm-panel.cm-panel-lint": {
		position: "relative",
		"& ul": {
			maxHeight: "100px",
			overflowY: "auto",
			"& [aria-selected]": {
				backgroundColor: "#ddd",
				"& u": { textDecoration: "underline" }
			},
			"&:focus [aria-selected]": {
				background_fallback: "#bdf",
				backgroundColor: "Highlight",
				color_fallback: "white",
				color: "HighlightText"
			},
			"& u": { textDecoration: "none" },
			padding: 0,
			margin: 0
		},
		"& [name=close]": {
			position: "absolute",
			top: "0",
			right: "2px",
			background: "inherit",
			border: "none",
			font: "inherit",
			padding: 0,
			margin: 0
		}
	},
	"&dark .cm-lintRange-active": { backgroundColor: "#86714a80" },
	"&dark .cm-panel.cm-panel-lint ul": { "& [aria-selected]": { backgroundColor: "#2e343e" } }
});
function ho(e) {
	return e == "error" ? 4 : e == "warning" ? 3 : e == "info" ? 2 : 1;
}
function go(e) {
	let t = "hint", n = 1;
	for (let r of e) {
		let e = ho(r.severity);
		e > n && (n = e, t = r.severity);
	}
	return t;
}
var _o = /*@__PURE__*/ se($a, { hideOn: qa }), vo = [
	X,
	/*@__PURE__*/ b.decorations.compute([X], (e) => {
		let { selected: t, panel: n } = e.field(X);
		return !t || !n || t.from == t.to ? C.none : C.set([Qa.range(t.from, t.to)]);
	}),
	_o,
	mo
], yo = [
	ne(),
	xe(),
	we(),
	Bn(),
	Ne(),
	le(),
	ue(),
	c.allowMultipleSelections.of(!0),
	ze(),
	Ee(Me, { fallback: !0 }),
	Pe(),
	rn(),
	xn(),
	Se(),
	be(),
	oe(),
	$i(),
	Ce.of([
		...un,
		...zi,
		...Ra,
		...rr,
		...Re,
		...Sn,
		...ro
	])
];
Ve([], "FeaturesCtx"), Ve({}, "CrepeCtx");
function bo(e) {
	return e.use("FeaturesCtx");
}
function xo(e) {
	return (t) => {
		bo(t).update((t) => t.includes(e) ? t : [...t, e]);
	};
}
var So = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    fill=\"none\"\n    viewBox=\"0 0 24 24\"\n    stroke-width=\"1.5\"\n    stroke=\"currentColor\"\n    class=\"w-6 h-6\"\n  >\n    <path\n      stroke-linecap=\"round\"\n      stroke-linejoin=\"round\"\n      d=\"M19.5 8.25l-7.5 7.5-7.5-7.5\"\n    />\n  </svg>\n", Co = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_1098_15553)\">\n      <path\n        d=\"M18.3007 5.70973C17.9107 5.31973 17.2807 5.31973 16.8907 5.70973L12.0007 10.5897L7.1107 5.69973C6.7207 5.30973 6.0907 5.30973 5.7007 5.69973C5.3107 6.08973 5.3107 6.71973 5.7007 7.10973L10.5907 11.9997L5.7007 16.8897C5.3107 17.2797 5.3107 17.9097 5.7007 18.2997C6.0907 18.6897 6.7207 18.6897 7.1107 18.2997L12.0007 13.4097L16.8907 18.2997C17.2807 18.6897 17.9107 18.6897 18.3007 18.2997C18.6907 17.9097 18.6907 17.2797 18.3007 16.8897L13.4107 11.9997L18.3007 7.10973C18.6807 6.72973 18.6807 6.08973 18.3007 5.70973Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_1098_15553\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", wo = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    height=\"24px\"\n    viewBox=\"0 -960 960 960\"\n    width=\"24px\"\n    fill=\"none\"\n  >\n    <path\n      d=\"M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z\"\n    />\n  </svg>\n", To = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_1013_1585)\">\n      <path\n        d=\"M14.06 9.02L14.98 9.94L5.92 19H5V18.08L14.06 9.02ZM17.66 3C17.41 3 17.15 3.1 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C18.17 3.09 17.92 3 17.66 3ZM14.06 6.19L3 17.25V21H6.75L17.81 9.94L14.06 6.19Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_1013_1585\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", Eo = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    fill=\"none\"\n    viewBox=\"0 0 24 24\"\n    stroke-width=\"1.5\"\n    stroke=\"currentColor\"\n    class=\"w-6 h-6\"\n  >\n    <path\n      stroke-linecap=\"round\"\n      stroke-linejoin=\"round\"\n      d=\"M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z\"\n    />\n  </svg>\n", Do = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    height=\"24px\"\n    viewBox=\"0 -960 960 960\"\n    width=\"24px\"\n  >\n    <path\n      d=\"m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-222q-35 11-70.5 16.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-5.5l-36-38q-11 3-21 4.5t-21 1.5q-75 0-127.5-52.5T300-500q0-11 1.5-21t4.5-21l-84-82Zm319 93Zm-151 75Z\"\n    />\n  </svg>\n", Oo = /* @__PURE__ */ ((e) => (e.CodeMirror = "code-mirror", e.ListItem = "list-item", e.LinkTooltip = "link-tooltip", e.Cursor = "cursor", e.ImageBlock = "image-block", e.BlockEdit = "block-edit", e.Toolbar = "toolbar", e.Placeholder = "placeholder", e.Table = "table", e.Latex = "latex", e.TopBar = "top-bar", e.AI = "ai", e))(Oo || {}), ko = Ve([], "CodeMirrorThemeCtx"), Ao = (e, t = {}) => {
	e.config(xo(Oo.CodeMirror)).config((e) => {
		let { languages: n = [], theme: r = [] } = t, i = Ce.of(zi.concat(Bi)), a = (e) => [
			i,
			yo,
			e,
			...t.extensions ?? []
		];
		e.inject(ko, r), e.use(ko).on((t) => {
			e.update(ge.key, (e) => ({
				...e,
				extensions: a(t)
			}));
		}), e.update(ge.key, (e) => ({
			extensions: a(r),
			languages: n,
			expandIcon: t.expandIcon || So,
			searchIcon: t.searchIcon || Eo,
			clearSearchIcon: t.clearSearchIcon || Co,
			searchPlaceholder: t.searchPlaceholder || "Search language",
			copyText: t.copyText || "Copy",
			copyIcon: t.copyIcon || wo,
			onCopy: t.onCopy || (() => {}),
			noResultText: t.noResultText || "No result",
			renderLanguage: t.renderLanguage || e.renderLanguage,
			renderPreview: t.renderPreview || e.renderPreview,
			previewToggleButton: (e) => [t.previewToggleIcon?.call(t, e) || (e ? To : Do), t.previewToggleText?.call(t, e) || (e ? "Edit" : "Hide")].map((e) => e.trim()).join(" "),
			previewLabel: t.previewLabel || e.previewLabel,
			previewLoading: t.previewLoading || e.previewLoading,
			previewOnlyByDefault: t.previewOnlyByDefault ?? e.previewOnlyByDefault
		}));
	}).use(ye);
};
// node_modules/.pnpm/@codemirror+language-data@6.5.2/node_modules/@codemirror/language-data/dist/index.js
function Z(e) {
	return new Ie(je.define(e));
}
function Q(e) {
	return import("./dist-BkljzH_9.js").then((t) => t.sql({ dialect: t[e] }));
}
var jo = [
	/*@__PURE__*/ E.of({
		name: "C",
		extensions: [
			"c",
			"h",
			"ino"
		],
		load() {
			return import("./dist-CMdzwTPS.js").then((e) => e.cpp());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "C++",
		alias: ["cpp"],
		extensions: [
			"cpp",
			"c++",
			"cc",
			"cxx",
			"hpp",
			"h++",
			"hh",
			"hxx"
		],
		load() {
			return import("./dist-CMdzwTPS.js").then((e) => e.cpp());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "CQL",
		alias: ["cassandra"],
		extensions: ["cql"],
		load() {
			return Q("Cassandra");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "CSS",
		extensions: ["css"],
		load() {
			return import("./dist-B2PElGV8.js").then((e) => e.i).then((e) => e.css());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Go",
		extensions: ["go"],
		load() {
			return import("./dist-oUC5f7y8.js").then((e) => e.go());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "HTML",
		alias: ["xhtml"],
		extensions: [
			"html",
			"htm",
			"handlebars",
			"hbs"
		],
		load() {
			return import("./dist-BEbuTC4G.js").then((e) => e.html());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Java",
		extensions: ["java"],
		load() {
			return import("./dist-CDiAgdK4.js").then((e) => e.java());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "JavaScript",
		alias: [
			"ecmascript",
			"js",
			"node"
		],
		extensions: [
			"js",
			"mjs",
			"cjs"
		],
		load() {
			return import("./dist-BFvpsUQ5.js").then((e) => e.t).then((e) => e.javascript());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Jinja",
		extensions: [
			"j2",
			"jinja",
			"jinja2"
		],
		load() {
			return import("./dist-C2NZpVwl.js").then((e) => e.jinja());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "JSON",
		alias: ["json5"],
		extensions: ["json", "map"],
		load() {
			return import("./dist-8Z_ikZdH.js").then((e) => e.json());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "JSX",
		extensions: ["jsx"],
		load() {
			return import("./dist-BFvpsUQ5.js").then((e) => e.t).then((e) => e.javascript({ jsx: !0 }));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "LESS",
		extensions: ["less"],
		load() {
			return import("./dist-BquHZEh8.js").then((e) => e.less());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Liquid",
		extensions: ["liquid"],
		load() {
			return import("./dist-VKLDRfnq.js").then((e) => e.liquid());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MariaDB SQL",
		load() {
			return Q("MariaSQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Markdown",
		extensions: [
			"md",
			"markdown",
			"mkd"
		],
		load() {
			return import("./dist-Ids8xhbi.js").then((e) => e.markdown());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MS SQL",
		load() {
			return Q("MSSQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MySQL",
		load() {
			return Q("MySQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "PHP",
		extensions: [
			"php",
			"php3",
			"php4",
			"php5",
			"php7",
			"phtml"
		],
		load() {
			return import("./dist-B46at5dF.js").then((e) => e.php());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "PLSQL",
		extensions: ["pls"],
		load() {
			return Q("PLSQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "PostgreSQL",
		load() {
			return Q("PostgreSQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Python",
		extensions: [
			"BUILD",
			"bzl",
			"py",
			"pyw"
		],
		filename: /^(BUCK|BUILD)$/,
		load() {
			return import("./dist-DZ0blAoa.js").then((e) => e.python());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Rust",
		extensions: ["rs"],
		load() {
			return import("./dist-DaKN27nX.js").then((e) => e.rust());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Sass",
		extensions: ["sass"],
		load() {
			return import("./dist-9S6MRSu9.js").then((e) => e.sass({ indented: !0 }));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SCSS",
		extensions: ["scss"],
		load() {
			return import("./dist-9S6MRSu9.js").then((e) => e.sass());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SQL",
		extensions: ["sql"],
		load() {
			return Q("StandardSQL");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SQLite",
		load() {
			return Q("SQLite");
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TSX",
		extensions: ["tsx"],
		load() {
			return import("./dist-BFvpsUQ5.js").then((e) => e.t).then((e) => e.javascript({
				jsx: !0,
				typescript: !0
			}));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TypeScript",
		alias: ["ts"],
		extensions: [
			"ts",
			"mts",
			"cts"
		],
		load() {
			return import("./dist-BFvpsUQ5.js").then((e) => e.t).then((e) => e.javascript({ typescript: !0 }));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "WebAssembly",
		extensions: ["wat", "wast"],
		load() {
			return import("./dist-CLYA_rFx.js").then((e) => e.wast());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "XML",
		alias: [
			"rss",
			"wsdl",
			"xsd"
		],
		extensions: [
			"xml",
			"xsl",
			"xsd",
			"svg"
		],
		load() {
			return import("./dist-LYAIF6l3.js").then((e) => e.xml());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "YAML",
		alias: ["yml"],
		extensions: ["yaml", "yml"],
		load() {
			return import("./dist-CNsULpU5.js").then((e) => e.yaml());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "APL",
		extensions: ["dyalog", "apl"],
		load() {
			return import("./apl-DAOTUjmJ.js").then((e) => Z(e.apl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "PGP",
		alias: ["asciiarmor"],
		extensions: [
			"asc",
			"pgp",
			"sig"
		],
		load() {
			return import("./asciiarmor-DkfbmyUC.js").then((e) => Z(e.asciiArmor));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "ASN.1",
		extensions: ["asn", "asn1"],
		load() {
			return import("./asn1-DAFnrk4c.js").then((e) => Z(e.asn1({})));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Asterisk",
		filename: /^extensions\.conf$/i,
		load() {
			return import("./asterisk-CGZXyAEA.js").then((e) => Z(e.asterisk));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Brainfuck",
		extensions: ["b", "bf"],
		load() {
			return import("./brainfuck-Cqj4uc68.js").then((e) => Z(e.brainfuck));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Cobol",
		extensions: ["cob", "cpy"],
		load() {
			return import("./cobol-BcPpxoTU.js").then((e) => Z(e.cobol));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "C#",
		alias: ["csharp", "cs"],
		extensions: ["cs"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.csharp));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Clojure",
		extensions: [
			"clj",
			"cljc",
			"cljx"
		],
		load() {
			return import("./clojure-CMAXb8E0.js").then((e) => Z(e.clojure));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "ClojureScript",
		extensions: ["cljs"],
		load() {
			return import("./clojure-CMAXb8E0.js").then((e) => Z(e.clojure));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Closure Stylesheets (GSS)",
		extensions: ["gss"],
		load() {
			return import("./css-FQWHOTIj.js").then((e) => Z(e.gss));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "CMake",
		extensions: ["cmake", "cmake.in"],
		filename: /^CMakeLists\.txt$/,
		load() {
			return import("./cmake-aAx0oeo4.js").then((e) => Z(e.cmake));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "CoffeeScript",
		alias: ["coffee", "coffee-script"],
		extensions: ["coffee"],
		load() {
			return import("./coffeescript-Bu8RiNbr.js").then((e) => Z(e.coffeeScript));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Common Lisp",
		alias: ["lisp"],
		extensions: [
			"cl",
			"lisp",
			"el"
		],
		load() {
			return import("./commonlisp-CdVE5yuH.js").then((e) => Z(e.commonLisp));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Cypher",
		extensions: ["cyp", "cypher"],
		load() {
			return import("./cypher-DoNeBDvn.js").then((e) => Z(e.cypher));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Cython",
		extensions: [
			"pyx",
			"pxd",
			"pxi"
		],
		load() {
			return import("./python-DcFFCEsS.js").then((e) => Z(e.cython));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Crystal",
		extensions: ["cr"],
		load() {
			return import("./crystal-D8QtbbMG.js").then((e) => Z(e.crystal));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "D",
		extensions: ["d"],
		load() {
			return import("./d-nKbRcN9b.js").then((e) => Z(e.d));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Dart",
		extensions: ["dart"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.dart));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "diff",
		extensions: ["diff", "patch"],
		load() {
			return import("./diff-BWVOyxsB.js").then((e) => Z(e.diff));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Dockerfile",
		filename: /^Dockerfile$/,
		load() {
			return import("./dockerfile-BWhLpc13.js").then((e) => Z(e.dockerFile));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "DTD",
		extensions: ["dtd"],
		load() {
			return import("./dtd-Cf4flc-z.js").then((e) => Z(e.dtd));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Dylan",
		extensions: [
			"dylan",
			"dyl",
			"intr"
		],
		load() {
			return import("./dylan-GMUcNHYD.js").then((e) => Z(e.dylan));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "EBNF",
		load() {
			return import("./ebnf-BRhxte40.js").then((e) => Z(e.ebnf));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "ECL",
		extensions: ["ecl"],
		load() {
			return import("./ecl-BWek2LdR.js").then((e) => Z(e.ecl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "edn",
		extensions: ["edn"],
		load() {
			return import("./clojure-CMAXb8E0.js").then((e) => Z(e.clojure));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Eiffel",
		extensions: ["e"],
		load() {
			return import("./eiffel-qP1GnPGe.js").then((e) => Z(e.eiffel));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Elm",
		extensions: ["elm"],
		load() {
			return import("./elm-v7eWW-0Z.js").then((e) => Z(e.elm));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Erlang",
		extensions: ["erl"],
		load() {
			return import("./erlang-CHlIXivQ.js").then((e) => Z(e.erlang));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Esper",
		load() {
			return import("./sql-_G2PSLGH.js").then((e) => Z(e.esper));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Factor",
		extensions: ["factor"],
		load() {
			return import("./factor-Cwxgl_6Y.js").then((e) => Z(e.factor));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "FCL",
		load() {
			return import("./fcl-IirCjJsg.js").then((e) => Z(e.fcl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Forth",
		extensions: [
			"forth",
			"fth",
			"4th"
		],
		load() {
			return import("./forth-eZnCNAiD.js").then((e) => Z(e.forth));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Fortran",
		extensions: [
			"f",
			"for",
			"f77",
			"f90",
			"f95"
		],
		load() {
			return import("./fortran--IwuQoMF.js").then((e) => Z(e.fortran));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "F#",
		alias: ["fsharp"],
		extensions: ["fs"],
		load() {
			return import("./mllike-BUNnvhLw.js").then((e) => Z(e.fSharp));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Gas",
		extensions: ["s"],
		load() {
			return import("./gas-D9YL64MR.js").then((e) => Z(e.gas));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Gherkin",
		extensions: ["feature"],
		load() {
			return import("./gherkin-CHTx1lSE.js").then((e) => Z(e.gherkin));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Groovy",
		extensions: ["groovy", "gradle"],
		filename: /^Jenkinsfile$/,
		load() {
			return import("./groovy-BUqGQkvn.js").then((e) => Z(e.groovy));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Haskell",
		extensions: ["hs"],
		load() {
			return import("./haskell-BJ0h2C5S.js").then((e) => Z(e.haskell));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Haxe",
		extensions: ["hx"],
		load() {
			return import("./haxe-Bg7TY0vR.js").then((e) => Z(e.haxe));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "HXML",
		extensions: ["hxml"],
		load() {
			return import("./haxe-Bg7TY0vR.js").then((e) => Z(e.hxml));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "HTTP",
		load() {
			return import("./http-DYbr-MwD.js").then((e) => Z(e.http));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "IDL",
		extensions: ["pro"],
		load() {
			return import("./idl-C6fi2Q6-.js").then((e) => Z(e.idl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "JSON-LD",
		alias: ["jsonld"],
		extensions: ["jsonld"],
		load() {
			return import("./javascript-BUKVl4dx.js").then((e) => Z(e.jsonld));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Julia",
		extensions: ["jl"],
		load() {
			return import("./julia-CZQzdWXv.js").then((e) => Z(e.julia));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Kotlin",
		extensions: ["kt", "kts"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.kotlin));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "LiveScript",
		alias: ["ls"],
		extensions: ["ls"],
		load() {
			return import("./livescript-b5hbS8Z3.js").then((e) => Z(e.liveScript));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Lua",
		extensions: ["lua"],
		load() {
			return import("./lua-B1j5N8Zu.js").then((e) => Z(e.lua));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "mIRC",
		extensions: ["mrc"],
		load() {
			return import("./mirc-Bme4Ui3t.js").then((e) => Z(e.mirc));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Mathematica",
		extensions: [
			"m",
			"nb",
			"wl",
			"wls"
		],
		load() {
			return import("./mathematica-DOh0ucds.js").then((e) => Z(e.mathematica));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Modelica",
		extensions: ["mo"],
		load() {
			return import("./modelica-D7HT-v5B.js").then((e) => Z(e.modelica));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MUMPS",
		extensions: ["mps"],
		load() {
			return import("./mumps-CXyPmqwI.js").then((e) => Z(e.mumps));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Mbox",
		extensions: ["mbox"],
		load() {
			return import("./mbox-Crm1ekE4.js").then((e) => Z(e.mbox));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Nginx",
		filename: /nginx.*\.conf$/i,
		load() {
			return import("./nginx--LhP2PTG.js").then((e) => Z(e.nginx));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "NSIS",
		extensions: ["nsh", "nsi"],
		load() {
			return import("./nsis-D3s0OMP7.js").then((e) => Z(e.nsis));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "NTriples",
		extensions: ["nt", "nq"],
		load() {
			return import("./ntriples-vCRyEjhG.js").then((e) => Z(e.ntriples));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Objective-C",
		alias: ["objective-c", "objc"],
		extensions: ["m"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.objectiveC));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Objective-C++",
		alias: ["objective-c++", "objc++"],
		extensions: ["mm"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.objectiveCpp));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "OCaml",
		extensions: [
			"ml",
			"mli",
			"mll",
			"mly"
		],
		load() {
			return import("./mllike-BUNnvhLw.js").then((e) => Z(e.oCaml));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Octave",
		extensions: ["m"],
		load() {
			return import("./octave-_7RAdtEm.js").then((e) => Z(e.octave));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Oz",
		extensions: ["oz"],
		load() {
			return import("./oz-DJaEZbpN.js").then((e) => Z(e.oz));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Pascal",
		extensions: ["p", "pas"],
		load() {
			return import("./pascal-CpqJQwun.js").then((e) => Z(e.pascal));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Perl",
		extensions: ["pl", "pm"],
		load() {
			return import("./perl-Dr18xZ4O.js").then((e) => Z(e.perl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Pig",
		extensions: ["pig"],
		load() {
			return import("./pig-Db5OcIHf.js").then((e) => Z(e.pig));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "PowerShell",
		extensions: [
			"ps1",
			"psd1",
			"psm1"
		],
		load() {
			return import("./powershell-D3oUVNIv.js").then((e) => Z(e.powerShell));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Properties files",
		alias: ["ini", "properties"],
		extensions: [
			"properties",
			"ini",
			"in"
		],
		load() {
			return import("./properties-BUwLDbV6.js").then((e) => Z(e.properties));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "ProtoBuf",
		extensions: ["proto"],
		load() {
			return import("./protobuf-Bq3kWTpI.js").then((e) => Z(e.protobuf));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Pug",
		alias: ["jade"],
		extensions: ["pug", "jade"],
		load() {
			return import("./pug-DurmDgc9.js").then((e) => Z(e.pug));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Puppet",
		extensions: ["pp"],
		load() {
			return import("./puppet-CStR0Ls7.js").then((e) => Z(e.puppet));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Q",
		extensions: ["q"],
		load() {
			return import("./q-CNfhog-D.js").then((e) => Z(e.q));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "R",
		alias: ["rscript"],
		extensions: ["r", "R"],
		load() {
			return import("./r-B08I36xf.js").then((e) => Z(e.r));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "RPM Changes",
		load() {
			return import("./rpm-DSA1RMPT.js").then((e) => Z(e.rpmChanges));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "RPM Spec",
		extensions: ["spec"],
		load() {
			return import("./rpm-DSA1RMPT.js").then((e) => Z(e.rpmSpec));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Ruby",
		alias: [
			"jruby",
			"macruby",
			"rake",
			"rb",
			"rbx"
		],
		extensions: ["rb"],
		filename: /^(Gemfile|Rakefile)$/,
		load() {
			return import("./ruby-B5j4oXoF.js").then((e) => Z(e.ruby));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SAS",
		extensions: ["sas"],
		load() {
			return import("./sas-BbDqp0Qx.js").then((e) => Z(e.sas));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Scala",
		extensions: ["scala"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.scala));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Scheme",
		extensions: ["scm", "ss"],
		load() {
			return import("./scheme-BgiHH8Wj.js").then((e) => Z(e.scheme));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Shell",
		alias: [
			"bash",
			"sh",
			"zsh"
		],
		extensions: [
			"sh",
			"ksh",
			"bash"
		],
		filename: /^PKGBUILD$/,
		load() {
			return import("./shell-DvhIPpgi.js").then((e) => Z(e.shell));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Sieve",
		extensions: ["siv", "sieve"],
		load() {
			return import("./sieve-DNL5zO2I.js").then((e) => Z(e.sieve));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Smalltalk",
		extensions: ["st"],
		load() {
			return import("./smalltalk-DhXhOvsV.js").then((e) => Z(e.smalltalk));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Solr",
		load() {
			return import("./solr-B4Go315h.js").then((e) => Z(e.solr));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SML",
		extensions: [
			"sml",
			"sig",
			"fun",
			"smackspec"
		],
		load() {
			return import("./mllike-BUNnvhLw.js").then((e) => Z(e.sml));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SPARQL",
		alias: ["sparul"],
		extensions: ["rq", "sparql"],
		load() {
			return import("./sparql-CryENGFv.js").then((e) => Z(e.sparql));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Spreadsheet",
		alias: ["excel", "formula"],
		load() {
			return import("./spreadsheet-DU5i2Z6w.js").then((e) => Z(e.spreadsheet));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Squirrel",
		extensions: ["nut"],
		load() {
			return import("./clike-4UmaX4th.js").then((e) => Z(e.squirrel));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Stylus",
		extensions: ["styl"],
		load() {
			return import("./stylus-BW3SURu1.js").then((e) => Z(e.stylus));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Swift",
		extensions: ["swift"],
		load() {
			return import("./swift-C4VMgzlm.js").then((e) => Z(e.swift));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "sTeX",
		load() {
			return import("./stex-Do_xXuO4.js").then((e) => Z(e.stex));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "LaTeX",
		alias: ["tex"],
		extensions: [
			"text",
			"ltx",
			"tex"
		],
		load() {
			return import("./stex-Do_xXuO4.js").then((e) => Z(e.stex));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "SystemVerilog",
		extensions: [
			"v",
			"sv",
			"svh"
		],
		load() {
			return import("./verilog-DzGXO4tb.js").then((e) => Z(e.verilog));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Tcl",
		extensions: ["tcl"],
		load() {
			return import("./tcl-WSKvha_r.js").then((e) => Z(e.tcl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Textile",
		extensions: ["textile"],
		load() {
			return import("./textile-CBicNMFj.js").then((e) => Z(e.textile));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TiddlyWiki",
		load() {
			return import("./tiddlywiki-C5lpJ0N4.js").then((e) => Z(e.tiddlyWiki));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Tiki wiki",
		load() {
			return import("./tiki-5CEQIBB5.js").then((e) => Z(e.tiki));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TOML",
		extensions: ["toml"],
		load() {
			return import("./toml-DKgINPfN.js").then((e) => Z(e.toml));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Troff",
		extensions: [
			"1",
			"2",
			"3",
			"4",
			"5",
			"6",
			"7",
			"8",
			"9"
		],
		load() {
			return import("./troff-CUNSy2t-.js").then((e) => Z(e.troff));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TTCN",
		extensions: [
			"ttcn",
			"ttcn3",
			"ttcnpp"
		],
		load() {
			return import("./ttcn-r9ltkO_m.js").then((e) => Z(e.ttcn));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "TTCN_CFG",
		extensions: ["cfg"],
		load() {
			return import("./ttcn-cfg-ChnizI51.js").then((e) => Z(e.ttcnCfg));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Turtle",
		extensions: ["ttl"],
		load() {
			return import("./turtle-BpAA6yNt.js").then((e) => Z(e.turtle));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Web IDL",
		extensions: ["webidl"],
		load() {
			return import("./webidl-DI8x0yDG.js").then((e) => Z(e.webIDL));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "VB.NET",
		extensions: ["vb"],
		load() {
			return import("./vb-Ca_XjHI9.js").then((e) => Z(e.vb));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "VBScript",
		extensions: ["vbs"],
		load() {
			return import("./vbscript-C-AVnjSx.js").then((e) => Z(e.vbScript));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Velocity",
		extensions: ["vtl"],
		load() {
			return import("./velocity-DkhrWbSN.js").then((e) => Z(e.velocity));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Verilog",
		extensions: ["v"],
		load() {
			return import("./verilog-DzGXO4tb.js").then((e) => Z(e.verilog));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "VHDL",
		extensions: ["vhd", "vhdl"],
		load() {
			return import("./vhdl-k5UWdFl7.js").then((e) => Z(e.vhdl));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "XQuery",
		extensions: [
			"xy",
			"xquery",
			"xq",
			"xqm",
			"xqy"
		],
		load() {
			return import("./xquery-CvCWWq6M.js").then((e) => Z(e.xQuery));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Yacas",
		extensions: ["ys"],
		load() {
			return import("./yacas-BioqmUvp.js").then((e) => Z(e.yacas));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Z80",
		extensions: ["z80"],
		load() {
			return import("./z80-SkgVR2uf.js").then((e) => Z(e.z80));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MscGen",
		extensions: [
			"mscgen",
			"mscin",
			"msc"
		],
		load() {
			return import("./mscgen-C_npc-2E.js").then((e) => Z(e.mscgen));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Xù",
		extensions: ["xu"],
		load() {
			return import("./mscgen-C_npc-2E.js").then((e) => Z(e.xu));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "MsGenny",
		extensions: ["msgenny"],
		load() {
			return import("./mscgen-C_npc-2E.js").then((e) => Z(e.msgenny));
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Vue",
		extensions: ["vue"],
		load() {
			return import("./dist-CVYr2zr8.js").then((e) => e.vue());
		}
	}),
	/*@__PURE__*/ E.of({
		name: "Angular Template",
		load() {
			return import("./dist-DsPtvnZ6.js").then((e) => e.angular());
		}
	})
], $ = (e) => `var(--nowo-milkdown-code-${e})`, Mo = Le.define([
	{
		tag: [
			T.keyword,
			T.modifier,
			T.operatorKeyword,
			T.controlKeyword,
			T.definitionKeyword,
			T.moduleKeyword
		],
		color: $("keyword")
	},
	{
		tag: [
			T.string,
			T.special(T.string),
			T.regexp,
			T.escape
		],
		color: $("string")
	},
	{
		tag: [
			T.comment,
			T.lineComment,
			T.blockComment,
			T.docComment
		],
		color: $("comment"),
		fontStyle: "italic"
	},
	{
		tag: [
			T.number,
			T.bool,
			T.null,
			T.atom,
			T.unit
		],
		color: $("number")
	},
	{
		tag: [
			T.function(T.variableName),
			T.function(T.propertyName),
			T.macroName
		],
		color: $("function")
	},
	{
		tag: [
			T.typeName,
			T.className,
			T.namespace,
			T.standard(T.typeName)
		],
		color: $("type")
	},
	{
		tag: [
			T.propertyName,
			T.attributeName,
			T.labelName
		],
		color: $("property")
	},
	{
		tag: [T.tagName, T.angleBracket],
		color: $("tag")
	},
	{
		tag: [
			T.meta,
			T.processingInstruction,
			T.annotation
		],
		color: $("meta")
	},
	{
		tag: [T.heading, T.strong],
		fontWeight: "700"
	},
	{
		tag: T.emphasis,
		fontStyle: "italic"
	},
	{
		tag: T.link,
		textDecoration: "underline"
	},
	{
		tag: T.invalid,
		color: $("invalid")
	}
]), No = /* @__PURE__ */ e({
	addCodeMirror: () => Fo,
	codeMirrorConfig: () => Po
});
function Po(e, t) {
	return {
		extensions: [
			b.cspNonce.of(e.nonce),
			b.contentAttributes.of({ "aria-label": O(e, "code.content") }),
			Ee(Mo)
		],
		languages: jo,
		searchPlaceholder: O(e, "code.language"),
		copyText: O(e, "code.copy"),
		noResultText: O(e, "suggest.empty"),
		previewLabel: O(e, "code.preview"),
		renderPreview: (n, r, i) => {
			if (!t || n.toLowerCase() !== "mermaid" || r.trim() === "") return null;
			He(r, e.nonce, e.dark(), O(e, "slash.mermaid")).then((e) => {
				let t = document.createElement("div");
				t.className = "nowo-md-mermaid", t.append(e), i(t);
			}, (e) => {
				let t = document.createElement("p");
				t.className = "nowo-milkdown-preview-error", t.textContent = e instanceof Error ? e.message : String(e), i(t);
			});
		}
	};
}
function Fo(e, t, n) {
	e.addFeature(Ao, Po(t, n));
}
export { $t as a, Fo as addCodeMirror, Po as codeMirrorConfig, qe as i, Ue as n, Ke as r, No as t };
