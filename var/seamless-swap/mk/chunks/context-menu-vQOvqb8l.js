import { u as e } from "./lib-DaU_YkmU.js";
import { at as t, ot as n, st as r, ut as i } from "./lib-9QpRoHAM.js";
import { a, c as o, i as s, l as c, o as l, r as u, u as d } from "./dist-DPp-l4A4.js";
import { a as f, i as p } from "./runtime-DBQRQGPT.js";
import { a as m, c as h, colorItems as g, i as _, l as v, n as y, o as b, r as x, s as S, t as C } from "./colors-CxE4grbu.js";
import { r as w, s as T, t as E } from "./keyboard-DXH1K7BX.js";
import { t as D } from "./file-view-D9RRAm5m.js";
import { o as O } from "./host-L1lfHHpX.js";
import { i as k, n as A, t as j } from "./context-menu-registry-Dw2l9wcD.js";
import { copyMarkdown as M, markdownIo as N, pasteMarkdown as P } from "./markdown-clipboard-BYweEQKH.js";
// src/Resources/assets/src/plugins/context-menu.ts
var F = new r("nowo-milkdown-context-menu"), I = () => typeof navigator < "u" && navigator.clipboard ? navigator.clipboard : {};
function L(e, t, n) {
	let r = e.doc.resolve(t), i = [], a = r.start();
	return r.parent.forEach((e) => {
		let t = n.isInSet(e.marks), r = i[i.length - 1];
		t && r && r.to === a && r.mark.eq(t) ? r.to = a + e.nodeSize : t && i.push({
			from: a,
			to: a + e.nodeSize,
			mark: t
		}), a += e.nodeSize;
	}), i.find((e) => e.from <= t && t <= e.to) ?? null;
}
var R = class {
	view;
	runtime;
	io;
	menu;
	onContextMenu = (e) => this.handleContextMenu(e);
	onKeyDown = (e) => {
		if (e.key !== "ContextMenu" && (e.key !== "F10" || !e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) || e.defaultPrevented || e.target?.closest?.(".cm-editor")) return;
		e.preventDefault(), E(this.view);
		let t = this.view.coordsAtPos(this.view.state.selection.head);
		this.open(t.left, t.bottom);
	};
	constructor(e, t, n = null) {
		this.view = e, this.runtime = t, this.io = n;
		let r = e.dom.closest(".nowo-milkdown") ?? document.body;
		this.menu = new C(r, f(t, "menu.label"), (t) => {
			t && e.focus();
		}), this.menu.icons = t.menuIcons, e.dom.addEventListener("contextmenu", this.onContextMenu), e.dom.addEventListener("keydown", this.onKeyDown);
	}
	update(e, t) {
		this.menu.isOpen && e.state.doc !== t.doc && this.menu.closeAll(!1);
	}
	destroy() {
		this.view.dom.removeEventListener("contextmenu", this.onContextMenu), this.view.dom.removeEventListener("keydown", this.onKeyDown), this.menu.destroy();
	}
	handleContextMenu(e) {
		if (e.shiftKey || e.defaultPrevented || e.pointerType === "touch" || e.target?.closest?.(".cm-editor, input, textarea, select")) return;
		let { view: n } = this, r = n.posAtCoords({
			left: e.clientX,
			top: e.clientY
		}), { from: a, to: o } = n.state.selection;
		if (r && (r.pos < a || r.pos > o)) {
			let e = r.inside >= 0 ? n.state.doc.nodeAt(r.inside) : null, a = e && (e.type.name === "leafDirective" && e.attrs.name === "file" || e.type.name === "image" && this.runtime.features.has("image_block"));
			n.dispatch(n.state.tr.setSelection(a ? t.create(n.state.doc, r.inside) : i.near(n.state.doc.resolve(r.pos))));
		}
		e.preventDefault(), this.open(e.clientX, e.clientY);
	}
	context() {
		let { view: e } = this, { from: t, to: n, empty: r } = e.state.selection;
		return {
			root: e.dom.closest(".nowo-milkdown") ?? e.dom,
			selection: {
				from: t,
				to: n,
				empty: r
			},
			text: e.state.doc.textBetween(t, n, "\n"),
			blockType: x(e.state)?.node.type.name ?? "doc",
			readonly: !e.editable,
			insertText: (t) => {
				e.editable && e.dispatch(e.state.tr.insertText(t).scrollIntoView());
			}
		};
	}
	groups() {
		let { view: e, runtime: t } = this, n = e.state, r = e.editable, i = h(e), p = (e) => () => i(e), b = (e, n, r = {}) => ({
			id: e,
			label: f(t, `menu.${e}`),
			run: n,
			...r
		}), x = I(), C = n.selection.empty, E = [];
		r && E.push(b("cut", () => this.exec("cut"), {
			shortcut: "Control+X",
			disabled: C
		})), E.push(b("copy", () => this.exec("copy"), {
			shortcut: "Control+C",
			disabled: C
		})), r && (E.push(b("paste", () => void this.paste(), {
			shortcut: "Control+V",
			disabled: !x.read,
			hint: f(t, "menu.paste_hint")
		})), E.push(b("paste_plain", () => void this.pastePlain(), {
			shortcut: "Control+Shift+V",
			disabled: !x.readText,
			hint: f(t, "menu.paste_hint")
		})));
		let A = this.io;
		if (A && (r && E.push(b("paste_markdown", () => void P(e, A, x), {
			shortcut: "Control+Alt+V",
			disabled: !x.readText,
			hint: f(t, "menu.paste_hint")
		})), E.push(b("copy_markdown", () => void M(n, A, x).then((e) => this.announce(e ? "clipboard.copied_markdown" : "clipboard.copy_failed")), {
			shortcut: "Control+Alt+Shift+C",
			disabled: C || !x.writeText
		}))), t.comment && !C) {
			let e = t.comment;
			E.push({
				id: "comment",
				label: f(t, "comments.action"),
				shortcut: "Control+Alt+M",
				run: () => e()
			});
		}
		let N = [E];
		if (r && t.insert.length && N.push([{
			id: "insert",
			label: f(t, "menu.insert"),
			items: t.insert.map((t) => ({
				id: `insert_${t.id}`,
				label: t.label,
				run: () => i(() => (t.inline || k(e), t.run(e), !0))
			}))
		}]), r) {
			let r = n.schema.marks.link ? L(n, n.selection.head, n.schema.marks.link) : null;
			if (r) {
				let e = O(String(r.mark.attrs.href ?? ""), "file", t.hostSchemes);
				N.push([
					b("link_open", () => window.open(e, "_blank", "noopener,noreferrer"), { disabled: e === "" }),
					b("link_copy", () => void x.writeText?.(e), { disabled: e === "" || !x.writeText }),
					b("link_remove", p((e, t) => {
						let n = e.schema.marks.link, r = L(e, e.selection.head, n);
						return r ? (t?.(e.tr.removeMark(r.from, r.to, n)), !0) : !1;
					}))
				]);
			}
			w(n) === "table" && N.push([
				b("row_above", p(l)),
				b("row_below", p(a)),
				b("column_left", p(s)),
				b("column_right", p(u)),
				b("delete_row", p(c)),
				b("delete_column", p(o)),
				b("delete_table", p(d))
			]);
			let i = y.filter((e) => v(e)(n)).map((e) => ({
				id: `turn_${e}`,
				label: f(t, `slash.${e}`),
				run: p(v(e))
			})), h = [];
			i.length && h.push({
				id: "turn_into",
				label: f(t, "menu.turn_into"),
				items: i
			}), t.features.has("colors") && h.push({
				id: "color",
				label: f(t, "menu.color"),
				items: g(n, t, !C, (e) => p(e)())
			}), h.push(...D(n, t, (e) => p(e)())), h.push(...T(e, t)), h.push(b("duplicate", p(m)), b("move_up", p(S(-1)), { disabled: !S(-1)(n) }), b("move_down", p(S(1)), { disabled: !S(1)(n) }), b("delete", p(_))), N.push(h);
		}
		let F = this.context(), R = [...j.values()].filter((e) => !e.when || e.when(F)).map((e) => ({
			id: `host:${e.id}`,
			label: e.label,
			shortcut: e.shortcut,
			run: () => e.run(this.context())
		}));
		return N.push(R), N;
	}
	open(e, t) {
		let n = {
			groups: this.groups(),
			context: this.context()
		}, r = new CustomEvent("nowo-milkdown:contextmenu", {
			bubbles: !0,
			cancelable: !0,
			detail: n
		});
		return n.context.root.dispatchEvent(r) ? (this.menu.open(n.groups, e, t), !0) : !1;
	}
	exec(e) {
		let { view: t } = this;
		t.focus();
		let n;
		try {
			n = document.execCommand(e);
		} catch {
			n = !1;
		}
		if (n) return;
		let { from: r, to: i } = t.state.selection, a = t.state.doc.textBetween(r, i, "\n");
		I().writeText?.(a), e === "cut" && t.editable && t.dispatch(t.state.tr.deleteSelection().scrollIntoView());
	}
	announce(e) {
		let t = this.view.dom.closest(".nowo-milkdown")?.querySelector(".nowo-milkdown__status");
		t && (t.textContent = f(this.runtime, e));
	}
	async paste() {
		let { view: e } = this, t = await I().read?.() ?? [];
		for (let n of t) {
			if (n.types.includes("text/html")) {
				e.pasteHTML(await (await n.getType("text/html")).text()), e.focus();
				return;
			}
			if (n.types.includes("text/plain")) {
				e.pasteText(await (await n.getType("text/plain")).text()), e.focus();
				return;
			}
		}
	}
	async pastePlain() {
		let { view: e } = this, t = await I().readText?.() ?? "";
		t !== "" && b(t, e.state.schema)(e.state, e.dispatch), e.focus();
	}
}, z = e((e) => new n({
	key: F,
	view: (t) => {
		let n = e.get(p.key);
		return new R(t, n, n.features.has("markdown_clipboard") ? N(e) : null);
	}
}));
export { R as ContextMenuView, F as contextMenuKey, z as contextMenuPlugin, L as markRange, A as registerContextMenuItem };
