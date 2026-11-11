import { u as e } from "./lib-DaU_YkmU.js";
import { at as t, ot as n, st as r, ut as i } from "./lib-9QpRoHAM.js";
import { a, i as o } from "./runtime-DBQRQGPT.js";
import { a as s, c, colorItems as l, i as u, l as d, n as f, s as p, t as m } from "./colors-CxE4grbu.js";
import { t as h } from "./file-view-D9RRAm5m.js";
import { s as g, t as _ } from "./keyboard-DXH1K7BX.js";
// src/Resources/assets/src/plugins/block-actions.ts
var v = new r("nowo-milkdown-block-actions"), y = /* @__PURE__ */ new WeakMap();
function b(e, t) {
	if (e.doc.childCount === 0) return null;
	let n = e.doc.resolve(Math.min(Math.max(t, 0), e.doc.content.size));
	return n.depth >= 1 ? n.before(1) : n.nodeAfter ? n.pos : null;
}
var x = class {
	view;
	runtime;
	drag;
	button;
	menu;
	target = null;
	targetDom = null;
	dragDoc = null;
	dragCancelled = !1;
	host;
	onMove = (e) => {
		let t = e.target instanceof Node ? e.target : null;
		for (; t && t.parentNode !== this.view.dom;) t = t.parentNode;
		if (t && this.view.state.doc.childCount !== 0) try {
			this.show(b(this.view.state, this.view.posAtDOM(t, 0)));
		} catch {}
	};
	onKey = (e) => this.handleKey(e);
	constructor(e, t, n) {
		this.view = e, this.runtime = t, this.drag = n, this.host = e.dom.closest(".nowo-milkdown") ?? document.body, this.button = document.createElement("button"), this.button.type = "button", this.button.tabIndex = -1, this.button.className = "nowo-milkdown-block-handle", this.button.setAttribute("aria-label", a(t, "block.actions")), this.button.setAttribute("aria-haspopup", "menu"), this.button.setAttribute("aria-expanded", "false"), this.button.setAttribute("aria-keyshortcuts", "Control+/"), this.button.textContent = "⋮⋮", this.button.hidden = !0, this.button.addEventListener("mousedown", (e) => e.preventDefault()), this.button.addEventListener("click", () => this.openFromButton()), n && (this.button.draggable = !0, this.button.addEventListener("dragstart", (e) => this.dragStart(e)), this.button.addEventListener("dragend", () => {
			this.view.dragging = null, this.dragDoc = null, this.dragCancelled = !1;
		})), this.host.append(this.button), this.host.classList.add("nowo-milkdown--blocks"), this.menu = new m(this.host, a(t, "block.actions"), (t) => {
			this.button.setAttribute("aria-expanded", "false"), t && e.focus();
		}), this.menu.icons = t.menuIcons, e.dom.addEventListener("mousemove", this.onMove), e.dom.addEventListener("keydown", this.onKey, !0);
	}
	update(e, t) {
		this.menu.isOpen && t && e.state.doc !== t.doc && this.menu.closeAll(!1), this.dragDoc !== null && e.state.doc !== this.dragDoc && (this.dragDoc = null, this.dragCancelled = !0, e.dragging = null), e.hasFocus() ? this.show(b(e.state, e.state.selection.from)) : this.target !== null && this.target >= e.state.doc.content.size && this.show(null);
	}
	destroy() {
		this.view.dom.removeEventListener("mousemove", this.onMove), this.view.dom.removeEventListener("keydown", this.onKey, !0), this.menu.destroy(), this.button.remove(), this.host.classList.remove("nowo-milkdown--blocks");
	}
	show(e) {
		this.target = e;
		let t = e === null ? null : this.view.nodeDOM(e);
		if (this.targetDom = t instanceof HTMLElement ? t : null, !t || typeof t.getBoundingClientRect != "function") {
			this.button.hidden = !0;
			return;
		}
		let n = t.getBoundingClientRect(), r = this.host.getBoundingClientRect();
		this.button.hidden = !1, this.button.style.top = `${Math.round(n.top - r.top)}px`, this.button.style.left = `${Math.round(Math.max(n.left - r.left - 32, 0))}px`;
	}
	handleKey(e) {
		if (!(e.defaultPrevented || e.target?.closest?.(".cm-editor"))) {
			if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key === "/") {
				e.preventDefault(), e.stopPropagation(), _(this.view);
				let t = this.view.coordsAtPos(this.view.state.selection.from);
				this.open(t.left, t.bottom);
			} else e.altKey && e.shiftKey && !e.ctrlKey && !e.metaKey && (e.key === "ArrowUp" || e.key === "ArrowDown") && (e.preventDefault(), e.stopPropagation(), this.move(e.key === "ArrowUp" ? -1 : 1));
		}
	}
	move(e) {
		let t = p(e)(this.view.state, this.view.dispatch), n = t ? e === -1 ? "block.moved_up" : "block.moved_down" : e === -1 ? "block.first" : "block.last";
		return this.announce(a(this.runtime, n)), t;
	}
	announce(e) {
		let t = this.host.querySelector(".nowo-milkdown__status");
		t && (t.textContent = e);
	}
	openFromButton() {
		if (this.target !== null) {
			let e = this.view.state.doc.resolve(Math.min(this.target + 1, this.view.state.doc.content.size)), n = this.view.state.doc.nodeAt(this.target), r = n?.type.name === "leafDirective" && n.attrs.name === "file";
			this.view.dispatch(this.view.state.tr.setSelection(r ? t.create(this.view.state.doc, this.target) : i.near(e)));
		}
		let e = this.button.getBoundingClientRect();
		this.open(e.left, e.bottom);
	}
	groups() {
		let { view: e, runtime: t } = this, n = e.state, r = c(e), i = f.filter((e) => d(e)(n)).map((e) => ({
			id: `turn_${e}`,
			label: a(t, `slash.${e}`),
			run: () => r(d(e))
		})), o = [];
		return i.length && o.push({
			id: "turn_into",
			label: a(t, "menu.turn_into"),
			items: i
		}), t.features.has("colors") && o.push({
			id: "color",
			label: a(t, "menu.color"),
			items: l(n, t, !1, r)
		}), e.editable && o.push(...h(n, t, r)), o.push(...g(e, t)), [
			o,
			[
				{
					id: "duplicate",
					label: a(t, "menu.duplicate"),
					run: () => r(s)
				},
				{
					id: "move_up",
					label: a(t, "menu.move_up"),
					shortcut: "Alt+Shift+ArrowUp",
					disabled: !p(-1)(n),
					run: () => r(() => this.move(-1))
				},
				{
					id: "move_down",
					label: a(t, "menu.move_down"),
					shortcut: "Alt+Shift+ArrowDown",
					disabled: !p(1)(n),
					run: () => r(() => this.move(1))
				}
			],
			[{
				id: "delete",
				label: a(t, "menu.delete"),
				run: () => r(u)
			}]
		];
	}
	open(e, t) {
		this.button.setAttribute("aria-expanded", "true"), this.menu.open(this.groups(), e, t);
	}
	dragStart(e) {
		let { view: n } = this, r = null, i = this.targetDom;
		if (i?.isConnected && i.parentNode === n.dom) try {
			r = b(n.state, n.posAtDOM(i, 0));
		} catch {
			r = null;
		}
		if (r === null) {
			e.preventDefault();
			return;
		}
		this.target = r;
		let a = t.create(n.state.doc, this.target);
		if (n.dispatch(n.state.tr.setSelection(a)), n.dragging = {
			slice: a.content(),
			move: !0
		}, this.dragDoc = n.state.doc, this.dragCancelled = !1, e.dataTransfer) {
			e.dataTransfer.effectAllowed = "move", e.dataTransfer.setData("text/plain", n.state.doc.textBetween(a.from, a.to, "\n"));
			let t = n.nodeDOM(this.target);
			t instanceof HTMLElement && e.dataTransfer.setDragImage(t, 0, 0);
		}
	}
};
function S(t) {
	return e((e) => new n({
		key: v,
		view: (n) => {
			let r = new x(n, e.get(o.key), t);
			return y.set(n, r), r;
		},
		props: { handleDrop: (e) => {
			let t = y.get(e);
			return t?.dragCancelled ? (t.dragCancelled = !1, !0) : !1;
		} }
	}));
}
export { x as BlockActionsView, v as blockActionsKey, S as blockActionsPlugin, b as topLevelPos };
