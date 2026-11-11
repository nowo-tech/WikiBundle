import { M as e, Ot as t, P as n, St as r, at as i, ct as a, ht as o, j as s, lt as c, ot as l, st as u, ut as d } from "./lib-9QpRoHAM.js";
// node_modules/.pnpm/prosemirror-tables@1.8.5/node_modules/prosemirror-tables/dist/index.js
var f, p;
if (typeof WeakMap < "u") {
	let e = /* @__PURE__ */ new WeakMap();
	f = (t) => e.get(t), p = (t, n) => (e.set(t, n), n);
} else {
	let e = [], t = 0;
	f = (t) => {
		for (let n = 0; n < e.length; n += 2) if (e[n] == t) return e[n + 1];
	}, p = (n, r) => (t == 10 && (t = 0), e[t++] = n, e[t++] = r);
}
var m = class {
	constructor(e, t, n, r) {
		this.width = e, this.height = t, this.map = n, this.problems = r;
	}
	findCell(e) {
		for (let t = 0; t < this.map.length; t++) {
			let n = this.map[t];
			if (n != e) continue;
			let r = t % this.width, i = t / this.width | 0, a = r + 1, o = i + 1;
			for (let e = 1; a < this.width && this.map[t + e] == n; e++) a++;
			for (let e = 1; o < this.height && this.map[t + this.width * e] == n; e++) o++;
			return {
				left: r,
				top: i,
				right: a,
				bottom: o
			};
		}
		throw RangeError(`No cell with offset ${e} found`);
	}
	colCount(e) {
		for (let t = 0; t < this.map.length; t++) if (this.map[t] == e) return t % this.width;
		throw RangeError(`No cell with offset ${e} found`);
	}
	nextCell(e, t, n) {
		let { left: r, right: i, top: a, bottom: o } = this.findCell(e);
		return t == "horiz" ? (n < 0 ? r == 0 : i == this.width) ? null : this.map[a * this.width + (n < 0 ? r - 1 : i)] : (n < 0 ? a == 0 : o == this.height) ? null : this.map[r + this.width * (n < 0 ? a - 1 : o)];
	}
	rectBetween(e, t) {
		let { left: n, right: r, top: i, bottom: a } = this.findCell(e), { left: o, right: s, top: c, bottom: l } = this.findCell(t);
		return {
			left: Math.min(n, o),
			top: Math.min(i, c),
			right: Math.max(r, s),
			bottom: Math.max(a, l)
		};
	}
	cellsInRect(e) {
		let t = [], n = {};
		for (let r = e.top; r < e.bottom; r++) for (let i = e.left; i < e.right; i++) {
			let a = r * this.width + i, o = this.map[a];
			n[o] || (n[o] = !0, i == e.left && i && this.map[a - 1] == o || r == e.top && r && this.map[a - this.width] == o || t.push(o));
		}
		return t;
	}
	positionAt(e, t, n) {
		for (let r = 0, i = 0;; r++) {
			let a = i + n.child(r).nodeSize;
			if (r == e) {
				let n = t + e * this.width, r = (e + 1) * this.width;
				for (; n < r && this.map[n] < i;) n++;
				return n == r ? a - 1 : this.map[n];
			}
			i = a;
		}
	}
	static get(e) {
		return f(e) || p(e, h(e));
	}
};
function h(e) {
	if (e.type.spec.tableRole != "table") throw RangeError("Not a table node: " + e.type.name);
	let t = g(e), n = e.childCount, r = [], i = 0, a = null, o = [];
	for (let e = 0, i = t * n; e < i; e++) r[e] = 0;
	for (let s = 0, c = 0; s < n; s++) {
		let l = e.child(s);
		c++;
		for (let e = 0;; e++) {
			for (; i < r.length && r[i] != 0;) i++;
			if (e == l.childCount) break;
			let u = l.child(e), { colspan: d, rowspan: f, colwidth: p } = u.attrs;
			for (let e = 0; e < f; e++) {
				if (e + s >= n) {
					(a ||= []).push({
						type: "overlong_rowspan",
						pos: c,
						n: f - e
					});
					break;
				}
				let l = i + e * t;
				for (let e = 0; e < d; e++) {
					r[l + e] == 0 ? r[l + e] = c : (a ||= []).push({
						type: "collision",
						row: s,
						pos: c,
						n: d - e
					});
					let n = p && p[e];
					if (n) {
						let r = (l + e) % t * 2, i = o[r];
						i == null || i != n && o[r + 1] == 1 ? (o[r] = n, o[r + 1] = 1) : i == n && o[r + 1]++;
					}
				}
			}
			i += d, c += u.nodeSize;
		}
		let u = (s + 1) * t, d = 0;
		for (; i < u;) r[i++] == 0 && d++;
		d && (a ||= []).push({
			type: "missing",
			row: s,
			n: d
		}), c++;
	}
	(t === 0 || n === 0) && (a ||= []).push({ type: "zero_sized" });
	let s = new m(t, n, r, a), c = !1;
	for (let e = 0; !c && e < o.length; e += 2) o[e] != null && o[e + 1] < n && (c = !0);
	return c && _(s, o, e), s;
}
function g(e) {
	let t = -1, n = !1;
	for (let r = 0; r < e.childCount; r++) {
		let i = e.child(r), a = 0;
		if (n) for (let t = 0; t < r; t++) {
			let n = e.child(t);
			for (let e = 0; e < n.childCount; e++) {
				let i = n.child(e);
				t + i.attrs.rowspan > r && (a += i.attrs.colspan);
			}
		}
		for (let e = 0; e < i.childCount; e++) {
			let t = i.child(e);
			a += t.attrs.colspan, t.attrs.rowspan > 1 && (n = !0);
		}
		t == -1 ? t = a : t != a && (t = Math.max(t, a));
	}
	return t;
}
function _(e, t, n) {
	e.problems ||= [];
	let r = {};
	for (let i = 0; i < e.map.length; i++) {
		let a = e.map[i];
		if (r[a]) continue;
		r[a] = !0;
		let o = n.nodeAt(a);
		if (!o) throw RangeError(`No cell with offset ${a} found`);
		let s = null, c = o.attrs;
		for (let n = 0; n < c.colspan; n++) {
			let r = t[(i + n) % e.width * 2];
			r == null || c.colwidth && c.colwidth[n] == r || ((s ||= ee(c))[n] = r);
		}
		s && e.problems.unshift({
			type: "colwidth mismatch",
			pos: a,
			colwidth: s
		});
	}
}
function ee(e) {
	if (e.colwidth) return e.colwidth.slice();
	let t = [];
	for (let n = 0; n < e.colspan; n++) t.push(0);
	return t;
}
function v(e, t) {
	if (typeof e == "string") return {};
	let n = e.getAttribute("data-colwidth"), r = n && /^\d+(,\d+)*$/.test(n) ? n.split(",").map((e) => Number(e)) : null, i = Number(e.getAttribute("colspan") || 1), a = {
		colspan: i,
		rowspan: Number(e.getAttribute("rowspan") || 1),
		colwidth: r && r.length == i ? r : null
	};
	for (let n in t) {
		let r = t[n].getFromDOM, i = r && r(e);
		i != null && (a[n] = i);
	}
	return a;
}
function te(e, t) {
	let n = {};
	e.attrs.colspan != 1 && (n.colspan = e.attrs.colspan), e.attrs.rowspan != 1 && (n.rowspan = e.attrs.rowspan), e.attrs.colwidth && (n["data-colwidth"] = e.attrs.colwidth.join(","));
	for (let r in t) {
		let i = t[r].setDOMAttr;
		i && i(e.attrs[r], n);
	}
	return n;
}
function ne(e) {
	if (e !== null) {
		if (!Array.isArray(e)) throw TypeError("colwidth must be null or an array");
		for (let t of e) if (typeof t != "number") throw TypeError("colwidth must be null or an array of numbers");
	}
}
function re(e) {
	let t = e.cellAttributes || {}, n = {
		colspan: {
			default: 1,
			validate: "number"
		},
		rowspan: {
			default: 1,
			validate: "number"
		},
		colwidth: {
			default: null,
			validate: ne
		}
	};
	for (let e in t) n[e] = {
		default: t[e].default,
		validate: t[e].validate
	};
	return {
		table: {
			content: "table_row+",
			tableRole: "table",
			isolating: !0,
			group: e.tableGroup,
			parseDOM: [{ tag: "table" }],
			toDOM() {
				return ["table", ["tbody", 0]];
			}
		},
		table_row: {
			content: "(table_cell | table_header)*",
			tableRole: "row",
			parseDOM: [{ tag: "tr" }],
			toDOM() {
				return ["tr", 0];
			}
		},
		table_cell: {
			content: e.cellContent,
			attrs: n,
			tableRole: "cell",
			isolating: !0,
			parseDOM: [{
				tag: "td",
				getAttrs: (e) => v(e, t)
			}],
			toDOM(e) {
				return [
					"td",
					te(e, t),
					0
				];
			}
		},
		table_header: {
			content: e.cellContent,
			attrs: n,
			tableRole: "header_cell",
			isolating: !0,
			parseDOM: [{
				tag: "th",
				getAttrs: (e) => v(e, t)
			}],
			toDOM(e) {
				return [
					"th",
					te(e, t),
					0
				];
			}
		}
	};
}
function y(e) {
	let t = e.cached.tableNodeTypes;
	if (!t) {
		t = e.cached.tableNodeTypes = {};
		for (let n in e.nodes) {
			let r = e.nodes[n], i = r.spec.tableRole;
			i && (t[i] = r);
		}
	}
	return t;
}
var b = new u("selectingCells");
function x(e) {
	for (let t = e.depth - 1; t > 0; t--) if (e.node(t).type.spec.tableRole == "row") return e.node(0).resolve(e.before(t + 1));
	return null;
}
function S(e) {
	let t = e.selection.$head;
	for (let e = t.depth; e > 0; e--) if (t.node(e).type.spec.tableRole == "row") return !0;
	return !1;
}
function C(e) {
	let t = e.selection;
	if ("$anchorCell" in t && t.$anchorCell) return t.$anchorCell.pos > t.$headCell.pos ? t.$anchorCell : t.$headCell;
	if ("node" in t && t.node && t.node.type.spec.tableRole == "cell") return t.$anchor;
	let n = x(t.$head) || ie(t.$head);
	if (n) return n;
	throw RangeError(`No cell found around position ${t.head}`);
}
function ie(e) {
	for (let t = e.nodeAfter, n = e.pos; t; t = t.firstChild, n++) {
		let r = t.type.spec.tableRole;
		if (r == "cell" || r == "header_cell") return e.doc.resolve(n);
	}
	for (let t = e.nodeBefore, n = e.pos; t; t = t.lastChild, n--) {
		let r = t.type.spec.tableRole;
		if (r == "cell" || r == "header_cell") return e.doc.resolve(n - t.nodeSize);
	}
}
function w(e) {
	return e.parent.type.spec.tableRole == "row" && !!e.nodeAfter;
}
function ae(e) {
	return e.node(0).resolve(e.pos + e.nodeAfter.nodeSize);
}
function T(e, t) {
	return e.depth == t.depth && e.pos >= t.start(-1) && e.pos <= t.end(-1);
}
function E(e, t, n) {
	let r = e.node(-1), i = m.get(r), a = e.start(-1), o = i.nextCell(e.pos - a, t, n);
	return o == null ? null : e.node(0).resolve(a + o);
}
function D(e, t, n = 1) {
	let r = {
		...e,
		colspan: e.colspan - n
	};
	return r.colwidth && (r.colwidth = r.colwidth.slice(), r.colwidth.splice(t, n), r.colwidth.some((e) => e > 0) || (r.colwidth = null)), r;
}
function oe(e, t, n = 1) {
	let r = {
		...e,
		colspan: e.colspan + n
	};
	if (r.colwidth) {
		r.colwidth = r.colwidth.slice();
		for (let e = 0; e < n; e++) r.colwidth.splice(t, 0, 0);
	}
	return r;
}
function se(e, t, n) {
	let r = y(t.type.schema).header_cell;
	for (let i = 0; i < e.height; i++) if (t.nodeAt(e.map[n + i * e.width]).type != r) return !1;
	return !0;
}
var O = class e extends a {
	constructor(e, t = e) {
		let n = e.node(-1), r = m.get(n), i = e.start(-1), a = r.rectBetween(e.pos - i, t.pos - i), o = e.node(0), s = r.cellsInRect(a).filter((e) => e != t.pos - i);
		s.unshift(t.pos - i);
		let l = s.map((e) => {
			let t = n.nodeAt(e);
			if (!t) throw RangeError(`No cell with offset ${e} found`);
			let r = i + e + 1;
			return new c(o.resolve(r), o.resolve(r + t.content.size));
		});
		super(l[0].$from, l[0].$to, l), this.$anchorCell = e, this.$headCell = t;
	}
	map(t, n) {
		let r = t.resolve(n.map(this.$anchorCell.pos)), i = t.resolve(n.map(this.$headCell.pos));
		if (w(r) && w(i) && T(r, i)) {
			let t = this.$anchorCell.node(-1) != r.node(-1);
			return t && this.isRowSelection() ? e.rowSelection(r, i) : t && this.isColSelection() ? e.colSelection(r, i) : new e(r, i);
		}
		return d.between(r, i);
	}
	content() {
		let e = this.$anchorCell.node(-1), n = m.get(e), i = this.$anchorCell.start(-1), a = n.rectBetween(this.$anchorCell.pos - i, this.$headCell.pos - i), o = {}, s = [];
		for (let t = a.top; t < a.bottom; t++) {
			let i = [];
			for (let r = t * n.width + a.left, s = a.left; s < a.right; s++, r++) {
				let t = n.map[r];
				if (o[t]) continue;
				o[t] = !0;
				let s = n.findCell(t), c = e.nodeAt(t);
				if (!c) throw RangeError(`No cell with offset ${t} found`);
				let l = a.left - s.left, u = s.right - a.right;
				if (l > 0 || u > 0) {
					let e = c.attrs;
					if (l > 0 && (e = D(e, 0, l)), u > 0 && (e = D(e, e.colspan - u, u)), s.left < a.left) {
						if (c = c.type.createAndFill(e), !c) throw RangeError(`Could not create cell with attrs ${JSON.stringify(e)}`);
					} else c = c.type.create(e, c.content);
				}
				if (s.top < a.top || s.bottom > a.bottom) {
					let e = {
						...c.attrs,
						rowspan: Math.min(s.bottom, a.bottom) - Math.max(s.top, a.top)
					};
					c = s.top < a.top ? c.type.createAndFill(e) : c.type.create(e, c.content);
				}
				i.push(c);
			}
			s.push(e.child(t).copy(r.from(i)));
		}
		let c = this.isColSelection() && this.isRowSelection() ? e : s;
		return new t(r.from(c), 1, 1);
	}
	replace(e, n = t.empty) {
		let r = e.steps.length, i = this.ranges;
		for (let a = 0; a < i.length; a++) {
			let { $from: o, $to: s } = i[a], c = e.mapping.slice(r);
			e.replace(c.map(o.pos), c.map(s.pos), a ? t.empty : n);
		}
		let o = a.findFrom(e.doc.resolve(e.mapping.slice(r).map(this.to)), -1);
		o && e.setSelection(o);
	}
	replaceWith(e, n) {
		this.replace(e, new t(r.from(n), 0, 0));
	}
	forEachCell(e) {
		let t = this.$anchorCell.node(-1), n = m.get(t), r = this.$anchorCell.start(-1), i = n.cellsInRect(n.rectBetween(this.$anchorCell.pos - r, this.$headCell.pos - r));
		for (let n = 0; n < i.length; n++) e(t.nodeAt(i[n]), r + i[n]);
	}
	isColSelection() {
		let e = this.$anchorCell.index(-1), t = this.$headCell.index(-1);
		if (Math.min(e, t) > 0) return !1;
		let n = e + this.$anchorCell.nodeAfter.attrs.rowspan, r = t + this.$headCell.nodeAfter.attrs.rowspan;
		return Math.max(n, r) == this.$headCell.node(-1).childCount;
	}
	static colSelection(t, n = t) {
		let r = t.node(-1), i = m.get(r), a = t.start(-1), o = i.findCell(t.pos - a), s = i.findCell(n.pos - a), c = t.node(0);
		return o.top <= s.top ? (o.top > 0 && (t = c.resolve(a + i.map[o.left])), s.bottom < i.height && (n = c.resolve(a + i.map[i.width * (i.height - 1) + s.right - 1]))) : (s.top > 0 && (n = c.resolve(a + i.map[s.left])), o.bottom < i.height && (t = c.resolve(a + i.map[i.width * (i.height - 1) + o.right - 1]))), new e(t, n);
	}
	isRowSelection() {
		let e = this.$anchorCell.node(-1), t = m.get(e), n = this.$anchorCell.start(-1), r = t.colCount(this.$anchorCell.pos - n), i = t.colCount(this.$headCell.pos - n);
		if (Math.min(r, i) > 0) return !1;
		let a = r + this.$anchorCell.nodeAfter.attrs.colspan, o = i + this.$headCell.nodeAfter.attrs.colspan;
		return Math.max(a, o) == t.width;
	}
	eq(t) {
		return t instanceof e && t.$anchorCell.pos == this.$anchorCell.pos && t.$headCell.pos == this.$headCell.pos;
	}
	static rowSelection(t, n = t) {
		let r = t.node(-1), i = m.get(r), a = t.start(-1), o = i.findCell(t.pos - a), s = i.findCell(n.pos - a), c = t.node(0);
		return o.left <= s.left ? (o.left > 0 && (t = c.resolve(a + i.map[o.top * i.width])), s.right < i.width && (n = c.resolve(a + i.map[i.width * (s.top + 1) - 1]))) : (s.left > 0 && (n = c.resolve(a + i.map[s.top * i.width])), o.right < i.width && (t = c.resolve(a + i.map[i.width * (o.top + 1) - 1]))), new e(t, n);
	}
	toJSON() {
		return {
			type: "cell",
			anchor: this.$anchorCell.pos,
			head: this.$headCell.pos
		};
	}
	static fromJSON(t, n) {
		return new e(t.resolve(n.anchor), t.resolve(n.head));
	}
	static create(t, n, r = n) {
		return new e(t.resolve(n), t.resolve(r));
	}
	getBookmark() {
		return new ce(this.$anchorCell.pos, this.$headCell.pos);
	}
};
O.prototype.visible = !1, a.jsonID("cell", O);
var ce = class e {
	constructor(e, t) {
		this.anchor = e, this.head = t;
	}
	map(t) {
		return new e(t.map(this.anchor), t.map(this.head));
	}
	resolve(e) {
		let t = e.resolve(this.anchor), n = e.resolve(this.head);
		return t.parent.type.spec.tableRole == "row" && n.parent.type.spec.tableRole == "row" && t.index() < t.parent.childCount && n.index() < n.parent.childCount && T(t, n) ? new O(t, n) : a.near(n, 1);
	}
};
function le(t) {
	if (!(t.selection instanceof O)) return null;
	let n = [];
	return t.selection.forEachCell((e, t) => {
		n.push(s.node(t, t + e.nodeSize, { class: "selectedCell" }));
	}), e.create(t.doc, n);
}
function ue({ $from: e, $to: t }) {
	if (e.pos == t.pos || e.pos < t.pos - 6) return !1;
	let n = e.pos, r = t.pos, i = e.depth;
	for (; i >= 0 && !(e.after(i + 1) < e.end(i)); i--, n++);
	for (let e = t.depth; e >= 0 && !(t.before(e + 1) > t.start(e)); e--, r--);
	return n == r && /row|table/.test(e.node(i).type.spec.tableRole);
}
function de({ $from: e, $to: t }) {
	let n, r;
	for (let t = e.depth; t > 0; t--) {
		let r = e.node(t);
		if (r.type.spec.tableRole === "cell" || r.type.spec.tableRole === "header_cell") {
			n = r;
			break;
		}
	}
	for (let e = t.depth; e > 0; e--) {
		let n = t.node(e);
		if (n.type.spec.tableRole === "cell" || n.type.spec.tableRole === "header_cell") {
			r = n;
			break;
		}
	}
	return n !== r && t.parentOffset === 0;
}
function fe(e, t, n) {
	let r = (t || e).selection, a = (t || e).doc, o, s;
	if (r instanceof i && (s = r.node.type.spec.tableRole)) {
		if (s == "cell" || s == "header_cell") o = O.create(a, r.from);
		else if (s == "row") {
			let e = a.resolve(r.from + 1);
			o = O.rowSelection(e, e);
		} else if (!n) {
			let e = m.get(r.node), t = r.from + 1, n = t + e.map[e.width * e.height - 1];
			o = O.create(a, t + 1, n);
		}
	} else r instanceof d && ue(r) ? o = d.create(a, r.from) : r instanceof d && de(r) && (o = d.create(a, r.$from.start(), r.$from.end()));
	return o && (t ||= e.tr).setSelection(o), t;
}
var pe = new u("fix-tables");
function k(e, t, n, r) {
	let i = e.childCount, a = t.childCount;
	outer: for (let o = 0, s = 0; o < a; o++) {
		let a = t.child(o);
		for (let t = s, r = Math.min(i, o + 3); t < r; t++) if (e.child(t) == a) {
			s = t + 1, n += a.nodeSize;
			continue outer;
		}
		r(a, n), s < i && e.child(s).sameMarkup(a) ? k(e.child(s), a, n + 1, r) : a.nodesBetween(0, a.content.size, r, n + 1), n += a.nodeSize;
	}
}
function me(e, t) {
	let n, r = (t, r) => {
		t.type.spec.tableRole == "table" && (n = he(e, t, r, n));
	};
	return t ? t.doc != e.doc && k(t.doc, e.doc, 0, r) : e.doc.descendants(r), n;
}
function he(e, t, n, r) {
	let i = m.get(t);
	if (!i.problems) return r;
	r ||= e.tr;
	let a = [];
	for (let e = 0; e < i.height; e++) a.push(0);
	for (let e = 0; e < i.problems.length; e++) {
		let o = i.problems[e];
		if (o.type == "collision") {
			let e = t.nodeAt(o.pos);
			if (!e) continue;
			let i = e.attrs;
			for (let e = 0; e < i.rowspan; e++) a[o.row + e] += o.n;
			r.setNodeMarkup(r.mapping.map(n + 1 + o.pos), null, D(i, i.colspan - o.n, o.n));
		} else if (o.type == "missing") a[o.row] += o.n;
		else if (o.type == "overlong_rowspan") {
			let e = t.nodeAt(o.pos);
			if (!e) continue;
			r.setNodeMarkup(r.mapping.map(n + 1 + o.pos), null, {
				...e.attrs,
				rowspan: e.attrs.rowspan - o.n
			});
		} else if (o.type == "colwidth mismatch") {
			let e = t.nodeAt(o.pos);
			if (!e) continue;
			r.setNodeMarkup(r.mapping.map(n + 1 + o.pos), null, {
				...e.attrs,
				colwidth: o.colwidth
			});
		} else if (o.type == "zero_sized") {
			let e = r.mapping.map(n);
			r.delete(e, e + t.nodeSize);
		}
	}
	let o, s;
	for (let e = 0; e < a.length; e++) a[e] && (o ??= e, s = e);
	for (let c = 0, l = n + 1; c < i.height; c++) {
		let n = t.child(c), i = l + n.nodeSize, u = a[c];
		if (u > 0) {
			let t = "cell";
			n.firstChild && (t = n.firstChild.type.spec.tableRole);
			let a = [];
			for (let n = 0; n < u; n++) {
				let n = y(e.schema)[t].createAndFill();
				n && a.push(n);
			}
			let d = (c == 0 || o == c - 1) && s == c ? l + 1 : i - 1;
			r.insert(r.mapping.map(d), a);
		}
		l = i;
	}
	return r.setMeta(pe, { fixTables: !0 });
}
function A(e) {
	let t = m.get(e), n = [], r = t.height, i = t.width;
	for (let a = 0; a < r; a++) {
		let r = [];
		for (let n = 0; n < i; n++) {
			let o = a * i + n, s = t.map[o];
			if (a > 0) {
				let e = o - i;
				if (s === t.map[e]) {
					r.push(null);
					continue;
				}
			}
			if (n > 0) {
				let e = o - 1;
				if (s === t.map[e]) {
					r.push(null);
					continue;
				}
			}
			r.push(e.nodeAt(s));
		}
		n.push(r);
	}
	return n;
}
function j(e, t) {
	let n = [], r = m.get(e), i = r.height, a = r.width;
	for (let o = 0; o < i; o++) {
		let i = e.child(o), s = [];
		for (let n = 0; n < a; n++) {
			let i = t[o][n];
			if (!i) continue;
			let a = r.map[o * r.width + n], c = e.nodeAt(a);
			if (!c) continue;
			let l = c.type.createChecked(i.attrs, i.content, i.marks);
			s.push(l);
		}
		let c = i.type.createChecked(i.attrs, s, i.marks);
		n.push(c);
	}
	return e.type.createChecked(e.attrs, n, e.marks);
}
function M(e, t, n, r) {
	let i = t[0] > n[0] ? -1 : 1, a = e.splice(t[0], t.length), o = +(a.length % 2 == 0), s;
	return s = r === -1 && i === 1 ? n[0] - 1 : r === 1 && i === -1 ? n[n.length - 1] - o + 1 : i === -1 ? n[0] : n[n.length - 1] - o, e.splice(s, 0, ...a), e;
}
function N(e) {
	return ge((e) => e.type.spec.tableRole === "table", e);
}
function ge(e, t) {
	for (let n = t.depth; n >= 0; --n) {
		let r = t.node(n);
		if (e(r)) return {
			node: r,
			pos: n === 0 ? 0 : t.before(n),
			start: t.start(n),
			depth: n
		};
	}
	return null;
}
function P(e, t) {
	let n = N(t.$from);
	if (!n) return;
	let r = m.get(n.node);
	if (!(e < 0 || e > r.width - 1)) return r.cellsInRect({
		left: e,
		right: e + 1,
		top: 0,
		bottom: r.height
	}).map((e) => {
		let t = n.node.nodeAt(e), r = e + n.start;
		return {
			pos: r,
			start: r + 1,
			node: t,
			depth: n.depth + 2
		};
	});
}
function F(e, t) {
	let n = N(t.$from);
	if (!n) return;
	let r = m.get(n.node);
	if (!(e < 0 || e > r.height - 1)) return r.cellsInRect({
		left: 0,
		right: r.width,
		top: e,
		bottom: e + 1
	}).map((e) => {
		let t = n.node.nodeAt(e), r = e + n.start;
		return {
			pos: r,
			start: r + 1,
			node: t,
			depth: n.depth + 2
		};
	});
}
function I(e, t, n = t) {
	let r = t, i = n;
	for (let n = t; n >= 0; n--) {
		let t = P(n, e.selection);
		t && t.forEach((e) => {
			let t = e.node.attrs.colspan + n - 1;
			t >= r && (r = n), t > i && (i = t);
		});
	}
	for (let n = t; n <= i; n++) {
		let t = P(n, e.selection);
		t && t.forEach((e) => {
			let t = e.node.attrs.colspan + n - 1;
			e.node.attrs.colspan > 1 && t > i && (i = t);
		});
	}
	let a = [];
	for (let t = r; t <= i; t++) {
		let n = P(t, e.selection);
		n && n.length > 0 && a.push(t);
	}
	r = a[0], i = a[a.length - 1];
	let o = P(r, e.selection), s = F(0, e.selection);
	if (!o || !s) return;
	let c = e.doc.resolve(o[o.length - 1].pos), l;
	for (let t = i; t >= r; t--) {
		let n = P(t, e.selection);
		if (n && n.length > 0) {
			for (let e = s.length - 1; e >= 0; e--) if (s[e].pos === n[0].pos) {
				l = n[0];
				break;
			}
			if (l) break;
		}
	}
	if (l) return {
		$anchor: c,
		$head: e.doc.resolve(l.pos),
		indexes: a
	};
}
function L(e, t, n = t) {
	let r = t, i = n;
	for (let n = t; n >= 0; n--) {
		let t = F(n, e.selection);
		t && t.forEach((e) => {
			let t = e.node.attrs.rowspan + n - 1;
			t >= r && (r = n), t > i && (i = t);
		});
	}
	for (let n = t; n <= i; n++) {
		let t = F(n, e.selection);
		t && t.forEach((e) => {
			let t = e.node.attrs.rowspan + n - 1;
			e.node.attrs.rowspan > 1 && t > i && (i = t);
		});
	}
	let a = [];
	for (let t = r; t <= i; t++) {
		let n = F(t, e.selection);
		n && n.length > 0 && a.push(t);
	}
	r = a[0], i = a[a.length - 1];
	let o = F(r, e.selection), s = P(0, e.selection);
	if (!o || !s) return;
	let c = e.doc.resolve(o[o.length - 1].pos), l;
	for (let t = i; t >= r; t--) {
		let n = F(t, e.selection);
		if (n && n.length > 0) {
			for (let e = s.length - 1; e >= 0; e--) if (s[e].pos === n[0].pos) {
				l = n[0];
				break;
			}
			if (l) break;
		}
	}
	if (l) return {
		$anchor: c,
		$head: e.doc.resolve(l.pos),
		indexes: a
	};
}
function R(e) {
	return e[0].map((t, n) => e.map((e) => e[n]));
}
function _e(e) {
	let { tr: t, originIndex: n, targetIndex: r, select: i, pos: a } = e, o = N(t.doc.resolve(a));
	if (!o) return !1;
	let s = I(t, n)?.indexes, c = I(t, r)?.indexes;
	if (!s || !c || s.includes(r)) return !1;
	let l = ve(o.node, s, c, 0);
	if (t.replaceWith(o.pos, o.pos + o.node.nodeSize, l), !i) return !0;
	let u = m.get(l), d = o.start, f = r, p = u.positionAt(u.height - 1, f, l), h = t.doc.resolve(d + p), g = u.positionAt(0, f, l), _ = t.doc.resolve(d + g);
	return t.setSelection(O.colSelection(h, _)), !0;
}
function ve(e, t, n, r) {
	let i = R(A(e));
	return i = M(i, t, n, r), i = R(i), j(e, i);
}
function ye(e) {
	let { tr: t, originIndex: n, targetIndex: r, select: i, pos: a } = e, o = N(t.doc.resolve(a));
	if (!o) return !1;
	let s = L(t, n)?.indexes, c = L(t, r)?.indexes;
	if (!s || !c || s.includes(r)) return !1;
	let l = be(o.node, s, c, 0);
	if (t.replaceWith(o.pos, o.pos + o.node.nodeSize, l), !i) return !0;
	let u = m.get(l), d = o.start, f = r, p = u.positionAt(f, u.width - 1, l), h = t.doc.resolve(d + p), g = u.positionAt(f, 0, l), _ = t.doc.resolve(d + g);
	return t.setSelection(O.rowSelection(h, _)), !0;
}
function be(e, t, n, r) {
	let i = A(e);
	return i = M(i, t, n, r), j(e, i);
}
function z(e) {
	let t = e.selection, n = C(e), r = n.node(-1), i = n.start(-1), a = m.get(r);
	return {
		...t instanceof O ? a.rectBetween(t.$anchorCell.pos - i, t.$headCell.pos - i) : a.findCell(n.pos - i),
		tableStart: i,
		map: a,
		table: r
	};
}
function B(e, { map: t, tableStart: n, table: r }, i) {
	let a = i > 0 ? -1 : 0;
	se(t, r, i + a) && (a = i == 0 || i == t.width ? null : 0);
	for (let o = 0; o < t.height; o++) {
		let s = o * t.width + i;
		if (i > 0 && i < t.width && t.map[s - 1] == t.map[s]) {
			let a = t.map[s], c = r.nodeAt(a);
			e.setNodeMarkup(e.mapping.map(n + a), null, oe(c.attrs, i - t.colCount(a))), o += c.attrs.rowspan - 1;
		} else {
			let c = a == null ? y(r.type.schema).cell : r.nodeAt(t.map[s + a]).type, l = t.positionAt(o, i, r);
			e.insert(e.mapping.map(n + l), c.createAndFill());
		}
	}
	return e;
}
function xe(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e);
		t(B(e.tr, n, n.left));
	}
	return !0;
}
function Se(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e);
		t(B(e.tr, n, n.right));
	}
	return !0;
}
function Ce(e, { map: t, table: n, tableStart: r }, i) {
	let a = e.mapping.maps.length;
	for (let o = 0; o < t.height;) {
		let s = o * t.width + i, c = t.map[s], l = n.nodeAt(c), u = l.attrs;
		if (i > 0 && t.map[s - 1] == c || i < t.width - 1 && t.map[s + 1] == c) e.setNodeMarkup(e.mapping.slice(a).map(r + c), null, D(u, i - t.colCount(c)));
		else {
			let t = e.mapping.slice(a).map(r + c);
			e.delete(t, t + l.nodeSize);
		}
		o += u.rowspan;
	}
}
function we(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e), r = e.tr;
		if (n.left == 0 && n.right == n.map.width) return !1;
		for (let e = n.right - 1; Ce(r, n, e), e != n.left; e--) {
			let e = n.tableStart ? r.doc.nodeAt(n.tableStart - 1) : r.doc;
			if (!e) throw RangeError("No table found");
			n.table = e, n.map = m.get(e);
		}
		t(r);
	}
	return !0;
}
function Te(e, t, n) {
	let r = y(t.type.schema).header_cell;
	for (let i = 0; i < e.width; i++) if (t.nodeAt(e.map[i + n * e.width])?.type != r) return !1;
	return !0;
}
function V(e, { map: t, tableStart: n, table: r }, i) {
	let a = n;
	for (let e = 0; e < i; e++) a += r.child(e).nodeSize;
	let o = [], s = i > 0 ? -1 : 0;
	Te(t, r, i + s) && (s = i == 0 || i == t.height ? null : 0);
	for (let a = 0, c = t.width * i; a < t.width; a++, c++) if (i > 0 && i < t.height && t.map[c] == t.map[c - t.width]) {
		let i = t.map[c], o = r.nodeAt(i).attrs;
		e.setNodeMarkup(n + i, null, {
			...o,
			rowspan: o.rowspan + 1
		}), a += o.colspan - 1;
	} else {
		let e = (s == null ? y(r.type.schema).cell : r.nodeAt(t.map[c + s * t.width])?.type)?.createAndFill();
		e && o.push(e);
	}
	return e.insert(a, y(r.type.schema).row.create(null, o)), e;
}
function Ee(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e);
		t(V(e.tr, n, n.top));
	}
	return !0;
}
function De(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e);
		t(V(e.tr, n, n.bottom));
	}
	return !0;
}
function Oe(e, { map: t, table: n, tableStart: r }, i) {
	let a = 0;
	for (let e = 0; e < i; e++) a += n.child(e).nodeSize;
	let o = a + n.child(i).nodeSize, s = e.mapping.maps.length;
	e.delete(a + r, o + r);
	let c = /* @__PURE__ */ new Set();
	for (let a = 0, o = i * t.width; a < t.width; a++, o++) {
		let l = t.map[o];
		if (!c.has(l)) {
			if (c.add(l), i > 0 && l == t.map[o - t.width]) {
				let t = n.nodeAt(l).attrs;
				e.setNodeMarkup(e.mapping.slice(s).map(l + r), null, {
					...t,
					rowspan: t.rowspan - 1
				}), a += t.colspan - 1;
			} else if (i < t.height && l == t.map[o + t.width]) {
				let o = n.nodeAt(l), c = o.attrs, u = o.type.create({
					...c,
					rowspan: o.attrs.rowspan - 1
				}, o.content), d = t.positionAt(i + 1, a, n);
				e.insert(e.mapping.slice(s).map(r + d), u), a += c.colspan - 1;
			}
		}
	}
}
function ke(e, t) {
	if (!S(e)) return !1;
	if (t) {
		let n = z(e), r = e.tr;
		if (n.top == 0 && n.bottom == n.map.height) return !1;
		for (let e = n.bottom - 1; Oe(r, n, e), e != n.top; e--) {
			let e = n.tableStart ? r.doc.nodeAt(n.tableStart - 1) : r.doc;
			if (!e) throw RangeError("No table found");
			n.table = e, n.map = m.get(n.table);
		}
		t(r);
	}
	return !0;
}
function Ae(e, t) {
	return function(n, r) {
		if (!S(n)) return !1;
		let i = C(n);
		if (i.nodeAfter.attrs[e] === t) return !1;
		if (r) {
			let a = n.tr;
			n.selection instanceof O ? n.selection.forEachCell((n, r) => {
				n.attrs[e] !== t && a.setNodeMarkup(r, null, {
					...n.attrs,
					[e]: t
				});
			}) : a.setNodeMarkup(i.pos, null, {
				...i.nodeAfter.attrs,
				[e]: t
			}), r(a);
		}
		return !0;
	};
}
function je(e) {
	return function(t, n) {
		if (!S(t)) return !1;
		if (n) {
			let r = y(t.schema), i = z(t), a = t.tr, o = i.map.cellsInRect(e == "column" ? {
				left: i.left,
				top: 0,
				right: i.right,
				bottom: i.map.height
			} : e == "row" ? {
				left: 0,
				top: i.top,
				right: i.map.width,
				bottom: i.bottom
			} : i), s = o.map((e) => i.table.nodeAt(e));
			for (let e = 0; e < o.length; e++) s[e].type == r.header_cell && a.setNodeMarkup(i.tableStart + o[e], r.cell, s[e].attrs);
			if (a.steps.length === 0) for (let e = 0; e < o.length; e++) a.setNodeMarkup(i.tableStart + o[e], r.header_cell, s[e].attrs);
			n(a);
		}
		return !0;
	};
}
function H(e, t, n) {
	let r = t.map.cellsInRect({
		left: 0,
		top: 0,
		right: e == "row" ? t.map.width : 1,
		bottom: e == "column" ? t.map.height : 1
	});
	for (let e = 0; e < r.length; e++) {
		let i = t.table.nodeAt(r[e]);
		if (i && i.type !== n.header_cell) return !1;
	}
	return !0;
}
function U(e, t) {
	return t ||= { useDeprecatedLogic: !1 }, t.useDeprecatedLogic ? je(e) : function(t, n) {
		if (!S(t)) return !1;
		if (n) {
			let r = y(t.schema), i = z(t), a = t.tr, o = H("row", i, r), s = H("column", i, r), c = (e === "column" ? o : e === "row" && s) ? 1 : 0, l = e == "column" ? {
				left: 0,
				top: c,
				right: 1,
				bottom: i.map.height
			} : e == "row" ? {
				left: c,
				top: 0,
				right: i.map.width,
				bottom: 1
			} : i, u = e == "column" ? s ? r.cell : r.header_cell : e == "row" ? o ? r.cell : r.header_cell : r.cell;
			i.map.cellsInRect(l).forEach((e) => {
				let t = e + i.tableStart, n = a.doc.nodeAt(t);
				n && a.setNodeMarkup(t, u, n.attrs);
			}), n(a);
		}
		return !0;
	};
}
U("row", { useDeprecatedLogic: !0 }), U("column", { useDeprecatedLogic: !0 }), U("cell", { useDeprecatedLogic: !0 });
function Me(e, t) {
	if (t < 0) {
		let t = e.nodeBefore;
		if (t) return e.pos - t.nodeSize;
		for (let t = e.index(-1) - 1, n = e.before(); t >= 0; t--) {
			let r = e.node(-1).child(t), i = r.lastChild;
			if (i) return n - 1 - i.nodeSize;
			n -= r.nodeSize;
		}
	} else {
		if (e.index() < e.parent.childCount - 1) return e.pos + e.nodeAfter.nodeSize;
		let t = e.node(-1);
		for (let n = e.indexAfter(-1), r = e.after(); n < t.childCount; n++) {
			let e = t.child(n);
			if (e.childCount) return r + 1;
			r += e.nodeSize;
		}
	}
	return null;
}
function Ne(e) {
	return function(t, n) {
		if (!S(t)) return !1;
		let r = Me(C(t), e);
		if (r == null) return !1;
		if (n) {
			let e = t.doc.resolve(r);
			n(t.tr.setSelection(d.between(e, ae(e))).scrollIntoView());
		}
		return !0;
	};
}
function Pe(e, t) {
	let n = e.selection.$anchor;
	for (let r = n.depth; r > 0; r--) if (n.node(r).type.spec.tableRole == "table") return t && t(e.tr.delete(n.before(r), n.after(r)).scrollIntoView()), !0;
	return !1;
}
function W(e, n) {
	let r = e.selection;
	if (!(r instanceof O)) return !1;
	if (n) {
		let i = e.tr, a = y(e.schema).cell.createAndFill().content;
		r.forEachCell((e, n) => {
			e.content.eq(a) || i.replace(i.mapping.map(n + 1), i.mapping.map(n + e.nodeSize - 1), new t(a, 0, 0));
		}), i.docChanged && n(i);
	}
	return !0;
}
function Fe(e) {
	return (t, n) => {
		let { from: r, to: i, select: a = !0, pos: o = t.selection.from } = e, s = t.tr;
		return ye({
			tr: s,
			originIndex: r,
			targetIndex: i,
			select: a,
			pos: o
		}) ? (n?.(s), !0) : !1;
	};
}
function Ie(e) {
	return (t, n) => {
		let { from: r, to: i, select: a = !0, pos: o = t.selection.from } = e, s = t.tr;
		return _e({
			tr: s,
			originIndex: r,
			targetIndex: i,
			select: a,
			pos: o
		}) ? (n?.(s), !0) : !1;
	};
}
function Le(e) {
	if (e.size === 0) return null;
	let { content: n, openStart: r, openEnd: i } = e;
	for (; n.childCount == 1 && (r > 0 && i > 0 || n.child(0).type.spec.tableRole == "table");) r--, i--, n = n.child(0).content;
	let a = n.child(0), o = a.type.spec.tableRole, s = a.type.schema, c = [];
	if (o == "row") for (let e = 0; e < n.childCount; e++) {
		let a = n.child(e).content, o = e ? 0 : Math.max(0, r - 1), l = e < n.childCount - 1 ? 0 : Math.max(0, i - 1);
		(o || l) && (a = G(y(s).row, new t(a, o, l)).content), c.push(a);
	}
	else if (o == "cell" || o == "header_cell") c.push(r || i ? G(y(s).row, new t(n, r, i)).content : n);
	else return null;
	return Re(s, c);
}
function Re(e, t) {
	let n = [];
	for (let e = 0; e < t.length; e++) {
		let r = t[e];
		for (let t = r.childCount - 1; t >= 0; t--) {
			let { rowspan: i, colspan: a } = r.child(t).attrs;
			for (let t = e; t < e + i; t++) n[t] = (n[t] || 0) + a;
		}
	}
	let i = 0;
	for (let e = 0; e < n.length; e++) i = Math.max(i, n[e]);
	for (let a = 0; a < n.length; a++) if (a >= t.length && t.push(r.empty), n[a] < i) {
		let o = y(e).cell.createAndFill(), s = [];
		for (let e = n[a]; e < i; e++) s.push(o);
		t[a] = t[a].append(r.from(s));
	}
	return {
		height: t.length,
		width: i,
		rows: t
	};
}
function G(e, t) {
	let n = e.createAndFill();
	return new o(n).replace(0, n.content.size, t).doc;
}
function ze({ width: e, height: t, rows: n }, i, a) {
	if (e != i) {
		let t = [], a = [];
		for (let e = 0; e < n.length; e++) {
			let o = n[e], s = [];
			for (let n = t[e] || 0, r = 0; n < i; r++) {
				let a = o.child(r % o.childCount);
				n + a.attrs.colspan > i && (a = a.type.createChecked(D(a.attrs, a.attrs.colspan, n + a.attrs.colspan - i), a.content)), s.push(a), n += a.attrs.colspan;
				for (let n = 1; n < a.attrs.rowspan; n++) t[e + n] = (t[e + n] || 0) + a.attrs.colspan;
			}
			a.push(r.from(s));
		}
		n = a, e = i;
	}
	if (t != a) {
		let e = [];
		for (let i = 0, o = 0; i < a; i++, o++) {
			let s = [], c = n[o % t];
			for (let e = 0; e < c.childCount; e++) {
				let t = c.child(e);
				i + t.attrs.rowspan > a && (t = t.type.create({
					...t.attrs,
					rowspan: Math.max(1, a - t.attrs.rowspan)
				}, t.content)), s.push(t);
			}
			e.push(r.from(s));
		}
		n = e, t = a;
	}
	return {
		width: e,
		height: t,
		rows: n
	};
}
function Be(e, t, n, i, a, o, s) {
	let c = e.doc.type.schema, l = y(c), u, d;
	if (a > t.width) for (let r = 0, o = 0; r < t.height; r++) {
		let c = n.child(r);
		o += c.nodeSize;
		let f = [], p;
		p = c.lastChild == null || c.lastChild.type == l.cell ? u ||= l.cell.createAndFill() : d ||= l.header_cell.createAndFill();
		for (let e = t.width; e < a; e++) f.push(p);
		e.insert(e.mapping.slice(s).map(o - 1 + i), f);
	}
	if (o > t.height) {
		let c = [];
		for (let e = 0, r = (t.height - 1) * t.width; e < Math.max(t.width, a); e++) {
			let i = e >= t.width ? !1 : n.nodeAt(t.map[r + e]).type == l.header_cell;
			c.push(i ? d ||= l.header_cell.createAndFill() : u ||= l.cell.createAndFill());
		}
		let f = l.row.create(null, r.from(c)), p = [];
		for (let e = t.height; e < o; e++) p.push(f);
		e.insert(e.mapping.slice(s).map(i + n.nodeSize - 2), p);
	}
	return !!(u || d);
}
function Ve(e, t, n, r, i, a, o, s) {
	if (o == 0 || o == t.height) return !1;
	let c = !1;
	for (let l = i; l < a; l++) {
		let i = o * t.width + l, a = t.map[i];
		if (t.map[i - t.width] == a) {
			c = !0;
			let i = n.nodeAt(a), { top: u, left: d } = t.findCell(a);
			e.setNodeMarkup(e.mapping.slice(s).map(a + r), null, {
				...i.attrs,
				rowspan: o - u
			}), e.insert(e.mapping.slice(s).map(t.positionAt(o, d, n)), i.type.createAndFill({
				...i.attrs,
				rowspan: u + i.attrs.rowspan - o
			})), l += i.attrs.colspan - 1;
		}
	}
	return c;
}
function He(e, t, n, r, i, a, o, s) {
	if (o == 0 || o == t.width) return !1;
	let c = !1;
	for (let l = i; l < a; l++) {
		let i = l * t.width + o, a = t.map[i];
		if (t.map[i - 1] == a) {
			c = !0;
			let i = n.nodeAt(a), u = t.colCount(a), d = e.mapping.slice(s).map(a + r);
			e.setNodeMarkup(d, null, D(i.attrs, o - u, i.attrs.colspan - (o - u))), e.insert(d + i.nodeSize, i.type.createAndFill(D(i.attrs, 0, o - u))), l += i.attrs.rowspan - 1;
		}
	}
	return c;
}
function Ue(e, n, r, i, a) {
	let o = r ? e.doc.nodeAt(r - 1) : e.doc;
	if (!o) throw Error("No table found");
	let s = m.get(o), { top: c, left: l } = i, u = l + a.width, d = c + a.height, f = e.tr, p = 0;
	function h() {
		if (o = r ? f.doc.nodeAt(r - 1) : f.doc, !o) throw Error("No table found");
		s = m.get(o), p = f.mapping.maps.length;
	}
	Be(f, s, o, r, u, d, p) && h(), Ve(f, s, o, r, l, u, c, p) && h(), Ve(f, s, o, r, l, u, d, p) && h(), He(f, s, o, r, c, d, l, p) && h(), He(f, s, o, r, c, d, u, p) && h();
	for (let e = c; e < d; e++) {
		let n = s.positionAt(e, l, o), i = s.positionAt(e, u, o);
		f.replace(f.mapping.slice(p).map(n + r), f.mapping.slice(p).map(i + r), new t(a.rows[e - c], 0, 0));
	}
	h(), f.setSelection(new O(f.doc.resolve(r + s.positionAt(c, l, o)), f.doc.resolve(r + s.positionAt(d - 1, u - 1, o)))), n(f);
}
var We = n({
	ArrowLeft: q("horiz", -1),
	ArrowRight: q("horiz", 1),
	ArrowUp: q("vert", -1),
	ArrowDown: q("vert", 1),
	"Shift-ArrowLeft": J("horiz", -1),
	"Shift-ArrowRight": J("horiz", 1),
	"Shift-ArrowUp": J("vert", -1),
	"Shift-ArrowDown": J("vert", 1),
	Backspace: W,
	"Mod-Backspace": W,
	Delete: W,
	"Mod-Delete": W
});
function K(e, t, n) {
	return !n.eq(e.selection) && (t && t(e.tr.setSelection(n).scrollIntoView()), !0);
}
function q(e, t) {
	return (n, r, i) => {
		if (!i) return !1;
		let o = n.selection;
		if (o instanceof O) return K(n, r, a.near(o.$headCell, t));
		if (e != "horiz" && !o.empty) return !1;
		let s = Je(i, e, t);
		if (s == null) return !1;
		if (e == "horiz") return K(n, r, a.near(n.doc.resolve(o.head + t), t));
		{
			let i = n.doc.resolve(s), o = E(i, e, t), c;
			return c = o ? a.near(o, 1) : t < 0 ? a.near(n.doc.resolve(i.before(-1)), -1) : a.near(n.doc.resolve(i.after(-1)), 1), K(n, r, c);
		}
	};
}
function J(e, t) {
	return (n, r, i) => {
		if (!i) return !1;
		let a = n.selection, o;
		if (a instanceof O) o = a;
		else {
			let r = Je(i, e, t);
			if (r == null) return !1;
			o = new O(n.doc.resolve(r));
		}
		let s = E(o.$headCell, e, t);
		return s ? K(n, r, new O(o.$anchorCell, s)) : !1;
	};
}
function Ge(e, t) {
	let n = e.state.doc, r = x(n.resolve(t));
	return r ? (e.dispatch(e.state.tr.setSelection(new O(r))), !0) : !1;
}
function Ke(e, t, n) {
	if (!S(e.state)) return !1;
	let i = Le(n), a = e.state.selection;
	if (a instanceof O) {
		i ||= {
			width: 1,
			height: 1,
			rows: [r.from(G(y(e.state.schema).cell, n))]
		};
		let t = a.$anchorCell.node(-1), o = a.$anchorCell.start(-1), s = m.get(t).rectBetween(a.$anchorCell.pos - o, a.$headCell.pos - o);
		return i = ze(i, s.right - s.left, s.bottom - s.top), Ue(e.state, e.dispatch, o, s, i), !0;
	}
	if (i) {
		let t = C(e.state), n = t.start(-1);
		return Ue(e.state, e.dispatch, n, m.get(t.node(-1)).findCell(t.pos - n), i), !0;
	}
	return !1;
}
function qe(e, t) {
	if (t.button != 0 || t.ctrlKey || t.metaKey) return;
	let n = Y(e, t.target), r;
	if (t.shiftKey && e.state.selection instanceof O) i(e.state.selection.$anchorCell, t), t.preventDefault();
	else if (t.shiftKey && n && (r = x(e.state.selection.$anchor)) != null && X(e, t)?.pos != r.pos) i(r, t), t.preventDefault();
	else if (!n) return;
	function i(t, n) {
		let r = X(e, n), i = b.getState(e.state) == null;
		if (!r || !T(t, r)) {
			if (i) r = t;
			else return;
		}
		let a = new O(t, r);
		if (i || !e.state.selection.eq(a)) {
			let n = e.state.tr.setSelection(a);
			i && n.setMeta(b, t.pos), e.dispatch(n);
		}
	}
	function a() {
		e.root.removeEventListener("mouseup", a), e.root.removeEventListener("dragstart", a), e.root.removeEventListener("mousemove", o), b.getState(e.state) != null && e.dispatch(e.state.tr.setMeta(b, -1));
	}
	function o(r) {
		let o = r, s = b.getState(e.state), c;
		if (s != null) c = e.state.doc.resolve(s);
		else if (Y(e, o.target) != n && (c = X(e, t), !c)) return a();
		c && i(c, o);
	}
	e.root.addEventListener("mouseup", a), e.root.addEventListener("dragstart", a), e.root.addEventListener("mousemove", o);
}
function Je(e, t, n) {
	if (!(e.state.selection instanceof d)) return null;
	let { $head: r } = e.state.selection;
	for (let i = r.depth - 1; i >= 0; i--) {
		let a = r.node(i);
		if ((n < 0 ? r.index(i) : r.indexAfter(i)) != (n < 0 ? 0 : a.childCount)) return null;
		if (a.type.spec.tableRole == "cell" || a.type.spec.tableRole == "header_cell") {
			let a = r.before(i), o = t == "vert" ? n > 0 ? "down" : "up" : n > 0 ? "right" : "left";
			return e.endOfTextblock(o) ? a : null;
		}
	}
	return null;
}
function Y(e, t) {
	for (; t && t != e.dom; t = t.parentNode) if (t.nodeName == "TD" || t.nodeName == "TH") return t;
	return null;
}
function X(e, t) {
	let n = e.posAtCoords({
		left: t.clientX,
		top: t.clientY
	});
	if (!n) return null;
	let { inside: r, pos: i } = n;
	return r >= 0 && x(e.state.doc.resolve(r)) || x(e.state.doc.resolve(i));
}
var Ye = class {
	constructor(e, t) {
		this.node = e, this.defaultCellMinWidth = t, this.dom = document.createElement("div"), this.dom.className = "tableWrapper", this.table = this.dom.appendChild(document.createElement("table")), this.table.style.setProperty("--default-cell-min-width", `${t}px`), this.colgroup = this.table.appendChild(document.createElement("colgroup")), Z(e, this.colgroup, this.table, t), this.contentDOM = this.table.appendChild(document.createElement("tbody"));
	}
	update(e) {
		return e.type == this.node.type && (this.node = e, Z(e, this.colgroup, this.table, this.defaultCellMinWidth), !0);
	}
	ignoreMutation(e) {
		return e.type == "attributes" && (e.target == this.table || this.colgroup.contains(e.target));
	}
};
function Z(e, t, n, r, i, a) {
	let o = 0, s = !0, c = t.firstChild, l = e.firstChild;
	if (l) {
		for (let e = 0, n = 0; e < l.childCount; e++) {
			let { colspan: u, colwidth: d } = l.child(e).attrs;
			for (let e = 0; e < u; e++, n++) {
				let l = i == n ? a : d && d[e], u = l ? l + "px" : "";
				if (o += l || r, l || (s = !1), c) c.style.width != u && (c.style.width = u), c = c.nextSibling;
				else {
					let e = document.createElement("col");
					e.style.width = u, t.appendChild(e);
				}
			}
		}
		for (; c;) {
			var u;
			let e = c.nextSibling;
			(u = c.parentNode) == null || u.removeChild(c), c = e;
		}
		s ? (n.style.width = o + "px", n.style.minWidth = "") : (n.style.width = "", n.style.minWidth = o + "px");
	}
}
var Q = new u("tableColumnResizing");
function Xe({ handleWidth: e = 5, cellMinWidth: t = 25, defaultCellMinWidth: n = 100, View: r = Ye, lastColumnResizable: i = !0 } = {}) {
	let a = new l({
		key: Q,
		state: {
			init(e, t) {
				var i;
				let o = (i = a.spec) == null || (i = i.props) == null ? void 0 : i.nodeViews, s = y(t.schema).table.name;
				return r && o && (o[s] = (e, t) => new r(e, n, t)), new Ze(-1, !1);
			},
			apply(e, t) {
				return t.apply(e);
			}
		},
		props: {
			attributes: (e) => {
				let t = Q.getState(e);
				return t && t.activeHandle > -1 ? { class: "resize-cursor" } : {};
			},
			handleDOMEvents: {
				mousemove: (t, n) => {
					Qe(t, n, e, i);
				},
				mouseleave: (e) => {
					$e(e);
				},
				mousedown: (e, r) => {
					et(e, r, t, n);
				}
			},
			decorations: (e) => {
				let t = Q.getState(e);
				if (t && t.activeHandle > -1) return ct(e, t.activeHandle);
			},
			nodeViews: {}
		}
	});
	return a;
}
var Ze = class e {
	constructor(e, t) {
		this.activeHandle = e, this.dragging = t;
	}
	apply(t) {
		let n = this, r = t.getMeta(Q);
		if (r && r.setHandle != null) return new e(r.setHandle, !1);
		if (r && r.setDragging !== void 0) return new e(n.activeHandle, r.setDragging);
		if (n.activeHandle > -1 && t.docChanged) {
			let r = t.mapping.map(n.activeHandle, -1);
			return w(t.doc.resolve(r)) || (r = -1), new e(r, n.dragging);
		}
		return n;
	}
};
function Qe(e, t, n, r) {
	if (!e.editable) return;
	let i = Q.getState(e.state);
	if (i && !i.dragging) {
		let a = nt(t.target), o = -1;
		if (a) {
			let { left: r, right: i } = a.getBoundingClientRect();
			t.clientX - r <= n ? o = rt(e, t, "left", n) : i - t.clientX <= n && (o = rt(e, t, "right", n));
		}
		if (o != i.activeHandle) {
			if (!r && o !== -1) {
				let t = e.state.doc.resolve(o), n = t.node(-1), r = m.get(n), i = t.start(-1);
				if (r.colCount(t.pos - i) + t.nodeAfter.attrs.colspan - 1 == r.width - 1) return;
			}
			at(e, o);
		}
	}
}
function $e(e) {
	if (!e.editable) return;
	let t = Q.getState(e.state);
	t && t.activeHandle > -1 && !t.dragging && at(e, -1);
}
function et(e, t, n, r) {
	if (!e.editable) return !1;
	let i = e.dom.ownerDocument.defaultView ?? window, a = Q.getState(e.state);
	if (!a || a.activeHandle == -1 || a.dragging) return !1;
	let o = e.state.doc.nodeAt(a.activeHandle), s = tt(e, a.activeHandle, o.attrs);
	e.dispatch(e.state.tr.setMeta(Q, { setDragging: {
		startX: t.clientX,
		startWidth: s
	} }));
	function c(t) {
		i.removeEventListener("mouseup", c), i.removeEventListener("mousemove", l);
		let r = Q.getState(e.state);
		r?.dragging && (ot(e, r.activeHandle, it(r.dragging, t, n)), e.dispatch(e.state.tr.setMeta(Q, { setDragging: null })));
	}
	function l(t) {
		if (!t.which) return c(t);
		let i = Q.getState(e.state);
		if (i && i.dragging) {
			let a = it(i.dragging, t, n);
			$(e, i.activeHandle, a, r);
		}
	}
	return $(e, a.activeHandle, s, r), i.addEventListener("mouseup", c), i.addEventListener("mousemove", l), t.preventDefault(), !0;
}
function tt(e, t, { colspan: n, colwidth: r }) {
	let i = r && r[r.length - 1];
	if (i) return i;
	let a = e.domAtPos(t), o = a.node.childNodes[a.offset].offsetWidth, s = n;
	if (r) for (let e = 0; e < n; e++) r[e] && (o -= r[e], s--);
	return o / s;
}
function nt(e) {
	for (; e && e.nodeName != "TD" && e.nodeName != "TH";) e = e.classList && e.classList.contains("ProseMirror") ? null : e.parentNode;
	return e;
}
function rt(e, t, n, r) {
	let i = n == "right" ? -r : r, a = e.posAtCoords({
		left: t.clientX + i,
		top: t.clientY
	});
	if (!a) return -1;
	let { pos: o } = a, s = x(e.state.doc.resolve(o));
	if (!s) return -1;
	if (n == "right") return s.pos;
	let c = m.get(s.node(-1)), l = s.start(-1), u = c.map.indexOf(s.pos - l);
	return u % c.width == 0 ? -1 : l + c.map[u - 1];
}
function it(e, t, n) {
	let r = t.clientX - e.startX;
	return Math.max(n, e.startWidth + r);
}
function at(e, t) {
	e.dispatch(e.state.tr.setMeta(Q, { setHandle: t }));
}
function ot(e, t, n) {
	let r = e.state.doc.resolve(t), i = r.node(-1), a = m.get(i), o = r.start(-1), s = a.colCount(r.pos - o) + r.nodeAfter.attrs.colspan - 1, c = e.state.tr;
	for (let e = 0; e < a.height; e++) {
		let t = e * a.width + s;
		if (e && a.map[t] == a.map[t - a.width]) continue;
		let r = a.map[t], l = i.nodeAt(r).attrs, u = l.colspan == 1 ? 0 : s - a.colCount(r);
		if (l.colwidth && l.colwidth[u] == n) continue;
		let d = l.colwidth ? l.colwidth.slice() : st(l.colspan);
		d[u] = n, c.setNodeMarkup(o + r, null, {
			...l,
			colwidth: d
		});
	}
	c.docChanged && e.dispatch(c);
}
function $(e, t, n, r) {
	let i = e.state.doc.resolve(t), a = i.node(-1), o = i.start(-1), s = m.get(a).colCount(i.pos - o) + i.nodeAfter.attrs.colspan - 1, c = e.domAtPos(i.start(-1)).node;
	for (; c && c.nodeName != "TABLE";) c = c.parentNode;
	c && Z(a, c.firstChild, c, r, s, n);
}
function st(e) {
	return Array(e).fill(0);
}
function ct(t, n) {
	let r = [], i = t.doc.resolve(n), a = i.node(-1);
	if (!a) return e.empty;
	let o = m.get(a), c = i.start(-1), l = o.colCount(i.pos - c) + i.nodeAfter.attrs.colspan - 1;
	for (let e = 0; e < o.height; e++) {
		let n = l + e * o.width;
		if ((l == o.width - 1 || o.map[n] != o.map[n + 1]) && (e == 0 || o.map[n] != o.map[n - o.width])) {
			let e = o.map[n], i = c + e + a.nodeAt(e).nodeSize - 1, l = document.createElement("div");
			l.className = "column-resize-handle", Q.getState(t)?.dragging && r.push(s.node(c + e, c + e + a.nodeAt(e).nodeSize, { class: "column-resize-dragging" })), r.push(s.widget(i, l));
		}
	}
	return e.create(t.doc, r);
}
function lt({ allowTableNodeSelection: e = !1 } = {}) {
	return new l({
		key: b,
		state: {
			init() {
				return null;
			},
			apply(e, t) {
				let n = e.getMeta(b);
				if (n != null) return n == -1 ? null : n;
				if (t == null || !e.docChanged) return t;
				let { deleted: r, pos: i } = e.mapping.mapResult(t);
				return r ? null : i;
			}
		},
		props: {
			decorations: le,
			handleDOMEvents: { mousedown: qe },
			createSelectionBetween(e) {
				return b.getState(e.state) == null ? null : e.state.selection;
			},
			handleTripleClick: Ge,
			handleKeyDown: We,
			handlePaste: Ke
		},
		appendTransaction(t, n, r) {
			return fe(r, me(r, n), e);
		}
	});
}
export { Ae as _, De as a, we as c, N as d, Ne as f, z as g, Fe as h, xe as i, ke as l, Ie as m, m as n, Ee as o, S as p, Se as r, Xe as s, O as t, Pe as u, lt as v, re as y };
