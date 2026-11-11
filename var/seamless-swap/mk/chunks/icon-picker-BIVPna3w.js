import { n as e, r as t, t as n } from "./search-BYXNAND9.js";
import { a as r, n as i } from "./runtime-DBQRQGPT.js";
import { i as a, n as o, r as s, t as c } from "./layer-N-kUiX52.js";
// src/Resources/assets/src/ui/icon-picker.ts
var l = 60, u = 64, d = 8, f = 0, p = null, m = class {
	opts;
	element;
	input;
	listbox;
	live;
	tabs = [];
	tab = "icons";
	options = [];
	index = 0;
	catalog = [];
	closed = !1;
	id = `nowo-milkdown-picker-${++f}`;
	onOutside = (e) => {
		this.element.contains(e.target) || this.close(!1);
	};
	constructor(e) {
		this.opts = e;
		let { runtime: t } = e;
		if (this.element = document.createElement("div"), this.element.id = this.id, this.element.className = "nowo-milkdown-picker", this.element.setAttribute("role", "dialog"), this.element.setAttribute("aria-label", r(t, "picker.label")), this.element.addEventListener("keydown", (e) => this.onKey(e)), e.emoji && e.icons !== !1) {
			let e = document.createElement("div");
			e.className = "nowo-milkdown-picker__tabs", e.setAttribute("role", "tablist"), e.setAttribute("aria-label", r(t, "picker.label"));
			for (let n of ["icons", "emoji"]) {
				let i = document.createElement("button");
				i.type = "button", i.className = "nowo-milkdown-picker__tab", i.id = `${this.id}-tab-${n}`, i.setAttribute("role", "tab"), i.setAttribute("aria-controls", `${this.id}-list`), i.dataset.tab = n, i.textContent = r(t, `picker.tab_${n}`), i.addEventListener("click", () => this.select(n, !0)), this.tabs.push(i), e.append(i);
			}
			this.element.append(e);
		}
		let n = document.createElement("div");
		n.className = "nowo-milkdown-picker__field";
		let i = t.menuIcons ? a("search", "nowo-milkdown-picker__search-icon") : null;
		i && n.append(i), this.input = document.createElement("input"), this.input.type = "search", this.input.className = "nowo-milkdown-picker__search", this.input.autocomplete = "off", this.input.maxLength = 64, this.input.spellcheck = !1, this.input.value = (e.query ?? "").slice(0, 64), this.input.placeholder = r(t, "picker.search"), this.input.setAttribute("aria-label", r(t, "picker.search")), this.input.setAttribute("role", "combobox"), this.input.setAttribute("aria-expanded", "true"), this.input.setAttribute("aria-autocomplete", "list"), this.input.setAttribute("aria-controls", `${this.id}-list`), this.input.addEventListener("input", () => this.render()), n.append(this.input), this.listbox = document.createElement("ul"), this.listbox.id = `${this.id}-list`, this.listbox.className = "nowo-milkdown-picker__list", this.listbox.setAttribute("role", "listbox"), this.listbox.addEventListener("mousedown", (e) => {
			let t = e.target.closest("[role=\"option\"]");
			t && (e.preventDefault(), this.index = Number(t.dataset.index), this.pick());
		}), this.live = document.createElement("div"), this.live.className = "nowo-milkdown-sr-only", this.live.setAttribute("aria-live", "polite"), this.element.append(n, this.listbox, this.live), e.emoji && (e.icons === !1 || e.current && !/^[a-z0-9-]+$/.test(e.current)) && (this.tab = "emoji");
	}
	get isOpen() {
		return !this.closed;
	}
	async show() {
		s(this.element, this.opts.host), o(this.element, this.opts.x, this.opts.y), document.addEventListener("pointerdown", this.onOutside, !0), this.select(this.tab, !1), this.input.focus();
		try {
			this.catalog = await this.opts.entries();
		} catch {
			this.catalog = [];
		}
		this.closed || this.render();
	}
	select(e, t) {
		this.tab = e;
		for (let t of this.tabs) {
			let n = t.dataset.tab === e;
			t.setAttribute("aria-selected", String(n)), t.tabIndex = n ? 0 : -1;
		}
		this.tabs.length ? this.listbox.setAttribute("aria-labelledby", `${this.id}-tab-${e}`) : this.listbox.setAttribute("aria-label", r(this.opts.runtime, e === "emoji" ? "picker.tab_emoji" : "picker.list")), this.render(), t && this.input.focus();
	}
	candidates() {
		let i = this.input.value.trim(), a = this.opts.allowNone ? [{
			value: null,
			label: r(this.opts.runtime, "picker.none"),
			kind: "none"
		}] : [];
		if (this.tab === "emoji") {
			let e = i.toLowerCase(), n = t.filter(([t]) => t.includes(e));
			return {
				options: [...a, ...n.map(([e, t]) => ({
					value: t,
					label: e,
					kind: "emoji"
				}))],
				total: n.length
			};
		}
		let o = n(this.opts.runtime.icons, this.catalog), { names: s, total: c } = e(o, i, 60);
		return {
			options: [...a, ...s.map((e) => ({
				value: e,
				label: e,
				kind: "icon"
			}))],
			total: c
		};
	}
	render() {
		let { runtime: e } = this.opts, { options: t, total: n } = this.candidates();
		this.options = t;
		let o = t.findIndex((e) => e.value === (this.opts.current ?? null) && (e.kind !== "none" || this.opts.allowNone));
		this.index = this.input.value.trim() === "" && o >= 0 ? o : 0, this.listbox.replaceChildren(), t.forEach((t, n) => {
			let r = document.createElement("li");
			r.id = `${this.id}-opt-${n}`, r.className = `nowo-milkdown-picker__option nowo-milkdown-picker__option--${t.kind}`, r.setAttribute("role", "option"), r.dataset.index = String(n), t.value !== null && (r.dataset.value = t.value);
			let o;
			t.kind === "icon" ? o = i(e, t.value) : t.kind === "none" ? o = a("ban", "nowo-milkdown-picker__art") : (o = document.createElement("span"), o.className = "nowo-milkdown-picker__emoji", o.setAttribute("aria-hidden", "true"), o.textContent = t.value), o && r.append(o);
			let s = document.createElement("span");
			s.className = "nowo-milkdown-picker__name", s.textContent = t.label, r.append(s), this.listbox.append(r);
		});
		let s = t.filter((e) => e.kind !== "none").length;
		if (!s) {
			let t = document.createElement("li");
			t.className = "nowo-milkdown-picker__empty", t.setAttribute("role", "presentation"), t.textContent = r(e, "picker.empty"), this.listbox.append(t);
		}
		this.live.textContent = s < n ? r(e, "picker.more", {
			shown: s,
			count: n
		}) : r(e, "picker.count", { count: n }), this.highlight();
	}
	highlight() {
		this.listbox.querySelectorAll("[role=\"option\"]").forEach((e, t) => e.setAttribute("aria-selected", String(t === this.index))), this.options.length ? this.input.setAttribute("aria-activedescendant", `${this.id}-opt-${this.index}`) : this.input.removeAttribute("aria-activedescendant"), this.listbox.querySelector("[aria-selected=\"true\"]")?.scrollIntoView?.({ block: "nearest" });
	}
	move(e, t) {
		let n = this.options.length;
		if (!n) return;
		let r = this.index + e;
		this.index = t ? (r + n) % n : Math.min(Math.max(r, 0), n - 1), this.highlight();
	}
	onKey(e) {
		let t = this.tabs.includes(e.target), n = !0;
		switch (e.key) {
			case "ArrowDown":
				t ? n = !1 : this.move(1, !0);
				break;
			case "ArrowUp":
				t ? n = !1 : this.move(-1, !0);
				break;
			case "ArrowLeft":
			case "ArrowRight":
				if (t) {
					let e = this.tab === "icons" ? "emoji" : "icons";
					this.select(e, !1), this.tabs.find((t) => t.dataset.tab === e)?.focus();
				} else n = !1;
				break;
			case "PageDown":
				this.move(d, !1);
				break;
			case "PageUp":
				this.move(-8, !1);
				break;
			case "Enter":
				t ? n = !1 : this.pick();
				break;
			case "Escape":
				this.close(!0);
				break;
			case "Tab": {
				let n = this.tabs.find((e) => e.dataset.tab === this.tab);
				n && !e.shiftKey && t ? this.input.focus() : n && e.shiftKey && !t ? n.focus() : this.close(!0);
				break;
			}
			default: n = !1;
		}
		n && (e.preventDefault(), e.stopPropagation());
	}
	pick() {
		let e = this.options[this.index];
		e && (this.teardown(), this.opts.onPick(e.value));
	}
	close(e) {
		this.closed || (this.teardown(), this.opts.onClose(e));
	}
	teardown() {
		this.closed = !0, document.removeEventListener("pointerdown", this.onOutside, !0), c(this.element), this.element.remove(), p === this && (p = null);
	}
};
function h(e) {
	p?.close(!1);
	let t = new m(e);
	return p = t, t.show(), t;
}
export { m as IconPicker, l as MAX_OPTIONS, u as MAX_QUERY, h as open };
