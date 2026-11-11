import { Et as e, Ot as t, St as n, Tt as r, _t as i, ct as a, gt as o, pt as s, vt as c, yt as l } from "./lib-9QpRoHAM.js";
// node_modules/.pnpm/prosemirror-schema-list@1.6.0/node_modules/prosemirror-schema-list/dist/index.js
function u(e, t = null) {
	return function(n, r) {
		let { $from: i, $to: a } = n.selection, o = i.blockRange(a);
		if (!o) return !1;
		let s = r ? n.tr : null;
		return d(s, o, e, t) ? (r && r(s.scrollIntoView()), !0) : !1;
	};
}
function d(e, t, n, i = null) {
	let a = !1, o = t, s = t.$from.doc;
	if (t.depth >= 2 && t.$from.node(t.depth - 1).type.compatibleContent(n) && t.startIndex == 0) {
		if (t.$from.index(t.depth - 1) == 0) return !1;
		let e = s.resolve(t.start - 2);
		o = new r(e, e, t.depth), t.endIndex < t.parent.childCount && (t = new r(t.$from, s.resolve(t.$to.end(t.depth)), t.depth)), a = !0;
	}
	let l = c(o, n, i, t);
	return l ? (e && f(e, t, l, a, n), !0) : !1;
}
function f(e, r, a, o, c) {
	let l = n.empty;
	for (let e = a.length - 1; e >= 0; e--) l = n.from(a[e].type.create(a[e].attrs, l));
	e.step(new s(r.start - (o ? 2 : 0), r.end, r.start, r.end, new t(l, 0, 0), a.length, !0));
	let u = 0;
	for (let e = 0; e < a.length; e++) a[e].type == c && (u = e + 1);
	let d = a.length - u, f = r.start + a.length - (o ? 2 : 0), p = r.parent;
	for (let t = r.startIndex, n = r.endIndex, a = !0; t < n; t++, a = !1) !a && d && i(e.doc, f, d) && (e.split(f, d), f += 2 * d), f += p.child(t).nodeSize;
	return e;
}
function p(t, n) {
	return t instanceof e ? n == t : t.indexOf(n) > -1;
}
function m(e, r) {
	return function(o, s) {
		let { $from: c, $to: l, node: u } = o.selection;
		if (u && u.isBlock || c.depth < 2 || !c.sameParent(l)) return !1;
		let d = c.node(-1);
		if (!p(e, d.type)) return !1;
		if (c.parent.content.size == 0 && c.node(-1).childCount == c.indexAfter(-1)) {
			if (c.depth == 3 || !p(e, c.node(-3).type) || c.index(-2) != c.node(-2).childCount - 1) return !1;
			if (s) {
				let e = n.empty, r = c.index(-1) ? 1 : c.index(-2) ? 2 : 3;
				for (let t = c.depth - r; t >= c.depth - 3; t--) e = n.from(c.node(t).copy(e));
				let i = c.indexAfter(-1) < c.node(-2).childCount ? 1 : c.indexAfter(-2) < c.node(-3).childCount ? 2 : 3;
				e = e.append(n.from(c.node(-3).type.createAndFill()));
				let l = c.before(c.depth - (r - 1)), u = o.tr.replace(l, c.after(-i), new t(e, 4 - r, 0)), d = -1;
				u.doc.nodesBetween(l, u.doc.content.size, (e, t) => {
					if (d > -1) return !1;
					e.isTextblock && e.content.size == 0 && (d = t + 1);
				}), d > -1 && u.setSelection(a.near(u.doc.resolve(d))), s(u.scrollIntoView());
			}
			return !0;
		}
		let f = l.pos == c.end() ? d.contentMatchAt(0).defaultType : null, m = o.tr.delete(c.pos, l.pos), h = f ? [r ? {
			type: d.type,
			attrs: r
		} : null, { type: f }] : void 0;
		return i(m.doc, c.pos, 2, h) ? (s && s(m.split(c.pos, 2, h).scrollIntoView()), !0) : !1;
	};
}
function h(e) {
	return function(t, n) {
		let { $from: r, $to: i } = t.selection, a = r.blockRange(i, (t) => t.childCount > 0 && p(e, t.firstChild.type));
		return a ? n ? p(e, r.node(a.depth - 1).type) ? g(t, n, a) : _(t, n, a) : !0 : !1;
	};
}
function g(e, i, a) {
	let c = e.tr, u = a.end, d = a.$to.end(a.depth), f = a.parent.child(a.endIndex - 1).type;
	u < d && (c.step(new s(u - 1, d, u, d, new t(n.from(f.create(null, a.parent.copy())), 1, 0), 1, !0)), a = new r(c.doc.resolve(a.$from.pos), c.doc.resolve(d), a.depth));
	let p = l(a);
	if (p == null) return !1;
	c.lift(a, p);
	let m = c.doc.resolve(c.mapping.map(u, -1) - 1);
	return o(c.doc, m.pos) && m.nodeBefore.type == m.nodeAfter.type && c.join(m.pos), i(c.scrollIntoView()), !0;
}
function _(e, r, i) {
	let a = e.tr, o = i.parent;
	for (let e = i.end, t = i.endIndex - 1, n = i.startIndex; t > n; t--) e -= o.child(t).nodeSize, a.delete(e - 1, e + 1);
	let c = a.doc.resolve(i.start), l = c.nodeAfter;
	if (a.mapping.map(i.end) != i.start + c.nodeAfter.nodeSize) return !1;
	let u = i.startIndex == 0, d = i.endIndex == o.childCount, f = c.node(-1), p = c.index(-1);
	if (!f.canReplace(p + +!u, p + 1, l.content.append(d ? n.empty : n.from(o)))) return !1;
	let m = c.pos, h = m + l.nodeSize;
	return a.step(new s(m - +!!u, h + +!!d, m + 1, h - 1, new t((u ? n.empty : n.from(o.copy(n.empty))).append(d ? n.empty : n.from(o.copy(n.empty))), +!u, +!d), +!u)), r(a.scrollIntoView()), !0;
}
function v(e) {
	return function(r, i) {
		let { $from: a, $to: o } = r.selection, c = a.blockRange(o, (t) => t.childCount > 0 && p(e, t.firstChild.type));
		if (!c) return !1;
		let l = c.startIndex;
		if (l == 0) return !1;
		let u = c.parent, d = u.child(l - 1);
		if (!p(e, d.type)) return !1;
		if (i) {
			let e = d.lastChild && d.lastChild.type == u.type, a = n.from(e ? d.lastChild.lastChild.type.create() : null), o = new t(n.from(d.type.create(null, n.from(u.type.create(null, a)))), e ? 3 : 1, 0), l = c.start, f = c.end;
			i(r.tr.step(new s(l - (e ? 3 : 1), f, l, f, o, 1, !0)).scrollIntoView());
		}
		return !0;
	};
}
export { u as i, v as n, m as r, h as t };
