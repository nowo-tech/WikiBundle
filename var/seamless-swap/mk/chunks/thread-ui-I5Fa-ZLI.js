import { a as e } from "./runtime-DBQRQGPT.js";
import { _ as t, l as n, o as r, t as i, u as a } from "./plugin-Cnor8ZP7.js";
import { n as o } from "./link-titles-Cs1oogn7.js";
var s = /* @__PURE__ */ new Set();
function c(e) {
	if (typeof e != "string" || e === "" || new TextEncoder().encode(e).length > 2048 || /[\x00-\x20\x7f\\"'<>`]/.test(e)) return null;
	if (e.startsWith("/")) return e.startsWith("//") ? null : e;
	let t = /^https:\/\/([^/?#]+)/i.exec(e)?.[1];
	if (!t || !/^[^@[\]:]+(?::\d{1,5})?$/.test(t)) return null;
	let n;
	try {
		n = new URL(e);
	} catch {
		return null;
	}
	return n.protocol === "https:" && n.hostname !== "" && n.username === "" && n.password === "" ? e : null;
}
function l(e, t, n) {
	let r = document.createElement("span");
	r.className = "nowo-milkdown-avatar", r.setAttribute("aria-hidden", "true"), r.textContent = Array.from(e)[0]?.toUpperCase() ?? "?", t && /^#[0-9a-f]{6}$/i.test(t) && (r.style.backgroundColor = t);
	let i = c(n);
	if (!i || s.has(i)) return r;
	let a = document.createElement("img");
	return a.className = "nowo-milkdown-avatar__image", a.setAttribute("alt", ""), a.setAttribute("width", "24"), a.setAttribute("height", "24"), a.setAttribute("loading", "lazy"), a.setAttribute("decoding", "async"), a.setAttribute("referrerpolicy", "same-origin"), a.addEventListener("load", () => r.classList.add("is-loaded"), { once: !0 }), a.addEventListener("error", () => {
		s.add(i), a.remove(), r.classList.remove("has-image", "is-loaded");
	}, { once: !0 }), a.setAttribute("src", i), r.classList.add("has-image"), r.append(a), r;
}
var u = 2e5, d = 1e4, f = (e) => e && typeof e == "object" && !Array.isArray(e) ? e : {}, p = (e) => typeof e == "string" && e.trim() !== "" ? e.slice(0, 80) : "?", m = (e) => typeof e == "string" && i.test(e) ? e : null, h = (e) => e === !0, g = (e) => typeof e == "string" && e.length > 0 && e.length <= 16 && !/[\s<>&"'`]/.test(e) && !/^[\x20-\x7e]+$/.test(e);
function _(e) {
	let n = f(e);
	if (typeof n.id != "string" || !t.test(n.id)) return null;
	let r = f(n.author), i = h(n.deleted), a = f(n.can), o = Array.isArray(n.reactions) ? n.reactions.slice(0, 20).flatMap((e) => {
		let t = f(e);
		return g(t.emoji) && typeof t.count == "number" && Number.isInteger(t.count) && t.count > 0 ? [{
			emoji: t.emoji,
			count: Math.min(t.count, 1e6),
			mine: h(t.mine)
		}] : [];
	}) : [];
	return {
		id: n.id,
		author: {
			name: p(r.name),
			avatarUrl: c(r.avatarUrl),
			color: typeof r.color == "string" && /^#[0-9a-f]{6}$/i.test(r.color) ? r.color : null
		},
		html: !i && typeof n.html == "string" ? n.html.slice(0, u) : "",
		createdAt: m(n.createdAt) ?? "",
		editedAt: m(n.editedAt),
		reactions: i ? [] : o,
		can: i ? {
			edit: !1,
			delete: !1
		} : {
			edit: h(a.edit),
			delete: h(a.delete)
		},
		deleted: i
	};
}
function v(e) {
	let t = n(e);
	if (!t) return null;
	let r = f(e), i = f(r.can), a = f(r.resolvedBy), o = Array.isArray(r.comments) ? r.comments.slice(0, 500).flatMap((e) => _(e) ?? []) : [];
	return {
		...t,
		count: t.count || o.filter((e) => !e.deleted).length,
		comments: o,
		can: {
			reply: h(i.reply),
			resolve: h(i.resolve),
			react: h(i.react)
		},
		resolvedBy: typeof a.name == "string" ? {
			name: p(a.name),
			avatarUrl: c(a.avatarUrl)
		} : null,
		resolvedAt: m(r.resolvedAt)
	};
}
var y = class {
	base;
	csrfToken;
	fetcher;
	constructor(e, t, n = (...e) => fetch(...e)) {
		this.base = e, this.csrfToken = t, this.fetcher = n;
	}
	async request(e, t, n) {
		let r = { Accept: "application/json" };
		e !== "GET" && (r["Content-Type"] = "application/json", this.csrfToken && (r["X-CSRF-Token"] = this.csrfToken));
		try {
			let i = await this.fetcher(this.base.replace(/\/+$/, "") + t, {
				method: e,
				headers: r,
				credentials: "same-origin",
				redirect: "error",
				...n === void 0 ? {} : { body: JSON.stringify(n) }
			});
			return i.ok ? {
				ok: !0,
				value: i.status === 204 ? null : await i.json()
			} : {
				ok: !1,
				status: i.status
			};
		} catch {
			return {
				ok: !1,
				status: 0
			};
		}
	}
	async thread(e, t, n) {
		let r = await this.request(e, t, n);
		if (!r.ok) return r;
		if (r.value === null) return {
			ok: !0,
			value: null
		};
		let i = v(r.value);
		return i ? {
			ok: !0,
			value: i
		} : {
			ok: !1,
			status: 0
		};
	}
	async list() {
		let e = await this.request("GET", "/threads");
		if (!e.ok) return e;
		let t = f(e.value);
		return {
			ok: !0,
			value: {
				threads: a(t.threads),
				page: a(t.page),
				truncated: h(t.truncated)
			}
		};
	}
	get = (e) => this.thread("GET", `/threads/${encodeURIComponent(e)}`);
	create = (e, t) => this.thread("POST", "/threads", {
		anchor: e,
		body: t.slice(0, d)
	});
	reply = (e, t) => this.thread("POST", `/threads/${encodeURIComponent(e)}/comments`, { body: t.slice(0, d) });
	resolve = (e, t) => this.thread("PATCH", `/threads/${encodeURIComponent(e)}`, { resolved: t });
	anchor = (e, t, n) => this.thread("PATCH", `/threads/${encodeURIComponent(e)}`, {
		anchor: t,
		base: n
	});
	edit = (e, t) => this.thread("PATCH", `/comments/${encodeURIComponent(e)}`, { body: t.slice(0, d) });
	remove = (e) => this.thread("DELETE", `/comments/${encodeURIComponent(e)}`);
	react = (e, t, n) => this.thread(n ? "PUT" : "DELETE", `/comments/${encodeURIComponent(e)}/reactions/${encodeURIComponent(t)}`);
}, b = /* @__PURE__ */ new Set(/* @__PURE__ */ "P.BR.STRONG.EM.DEL.S.CODE.PRE.BLOCKQUOTE.UL.OL.LI.A.SPAN.MARK.H1.H2.H3.H4.H5.H6.TABLE.THEAD.TBODY.TR.TH.TD".split(".")), x = /* @__PURE__ */ new Set([
	"SCRIPT",
	"STYLE",
	"TEMPLATE",
	"IFRAME",
	"OBJECT",
	"EMBED",
	"NOSCRIPT",
	"SVG",
	"MATH"
]), S = /^nowo-md-[a-z0-9-]{1,60}$/, C = 5e3;
function w(e, t = document) {
	let n = new DOMParser().parseFromString(`<body>${e}</body>`, "text/html").body, r = t.createDocumentFragment(), i = C, a = (e, n) => {
		for (let r of [...e.childNodes]) {
			if (--i < 0) return;
			if (r.nodeType === Node.TEXT_NODE) {
				n.appendChild(t.createTextNode(r.textContent ?? ""));
				continue;
			}
			if (!(r instanceof Element) || x.has(r.tagName.toUpperCase())) continue;
			let e = r.tagName.toUpperCase();
			if (!b.has(e)) {
				a(r, n);
				continue;
			}
			let s = t.createElement(e.toLowerCase()), c = (r.getAttribute("class") ?? "").split(/\s+/).filter((e) => S.test(e));
			c.length && (s.className = c.join(" "));
			for (let e of [
				"title",
				"datetime",
				"lang"
			]) {
				let t = r.getAttribute(e);
				t !== null && s.setAttribute(e, t.slice(0, 500));
			}
			if (e === "A") {
				let e = o(r.getAttribute("href") ?? "");
				e !== "" && s.setAttribute("href", e), s.setAttribute("rel", "noopener noreferrer nofollow");
			}
			n.appendChild(s), a(r, s);
		}
	};
	return a(n, r), r;
}
// src/Resources/assets/src/comments/thread-ui.ts
var T = [
	"👍",
	"❤️",
	"😄",
	"🎉",
	"👀",
	"✅"
], E = (e, t, n) => {
	let r = document.createElement(e);
	return r.className = t, n !== void 0 && (r.textContent = n), r;
}, D = ".nowo-milkdown, .nowo-md, [data-nowo-milkdown-options], [contenteditable]";
function O(e, t) {
	return e.isConnected && !t.contains(e) && !e.closest(D);
}
function k(e, t) {
	let n;
	try {
		n = [...document.querySelectorAll(e)];
	} catch {
		return null;
	}
	return n.find((e) => O(e, t)) ?? null;
}
var A = class {
	deps;
	element;
	list;
	pageList;
	truncated;
	composer;
	client;
	threads = /* @__PURE__ */ new Map();
	page = /* @__PURE__ */ new Map();
	details = /* @__PURE__ */ new Map();
	open_ = null;
	pending = void 0;
	drafts = /* @__PURE__ */ new Map();
	showResolved = !1;
	live = null;
	liveIds = /* @__PURE__ */ new Set();
	liveTimer = null;
	destroyed = !1;
	onAnchors = (e) => void this.saveAnchors(e.detail.anchors);
	onOrphaned = () => this.render();
	get host() {
		return this.deps.options.panel === "container";
	}
	mount(e) {
		return this.destroyed || !O(e, this.deps.root) ? !1 : (e.append(this.element), !0);
	}
	requestOpen(e) {
		r(this.deps.root, "comments-open-requested", { id: e });
	}
	constructor(t) {
		this.deps = t;
		let { runtime: n, options: r } = t;
		this.client = t.client ?? new y(r.url, r.csrfToken);
		let i = this.host, a = E(i ? "div" : "aside", `nowo-milkdown-comments nowo-milkdown-comments--${r.panel}`);
		i || a.setAttribute("aria-label", e(n, "comments.title"));
		let o = E("div", "nowo-milkdown-comments__head");
		i || o.append(E("h2", "nowo-milkdown-comments__title", e(n, "comments.title")));
		let s = E("label", "nowo-milkdown-comments__toggle"), c = document.createElement("input");
		if (c.type = "checkbox", c.addEventListener("change", () => {
			this.showResolved = c.checked, t.api().showResolved(c.checked), this.render();
		}), s.append(c, document.createTextNode(` ${e(n, "comments.show_resolved")}`)), o.append(s), this.truncated = E("p", "nowo-milkdown-comments__truncated", e(n, "comments.truncated")), this.truncated.hidden = !0, this.list = E("ul", "nowo-milkdown-comments__list"), this.pageList = E("ul", "nowo-milkdown-comments__list nowo-milkdown-comments__list--page"), this.composer = this.form(e(n, "comments.new_label"), e(n, "comments.send"), (e) => this.submitNew(e), () => this.closeComposer()), this.composer.hidden = !0, a.append(o, this.truncated, this.composer, this.list, E("h3", "nowo-milkdown-comments__subtitle", e(n, "comments.page")), this.pageList), r.canCreate) {
			let t = E("button", "nowo-milkdown__action nowo-milkdown-comments__add", e(n, "comments.add_page"));
			t.type = "button", t.addEventListener("click", () => this.create(null)), a.append(t);
		}
		this.element = a, i ? (r.container ? k(r.container, t.root) : null)?.append(a) : (t.root.classList.add("nowo-milkdown--with-comments"), t.surface.after(a)), t.root.addEventListener("nowo-milkdown:comment-anchors-changed", this.onAnchors), t.root.addEventListener("nowo-milkdown:comment-orphaned", this.onOrphaned), this.connectLive();
	}
	async load() {
		let e = await this.client.list();
		this.destroyed || (e.ok ? (this.threads = e.value.threads, this.page = e.value.page, this.truncated.hidden = !e.value.truncated, this.deps.api().set([...this.threads.values(), ...this.page.values()]), this.render()) : this.fail("list", e.status, "comments.load_failed"));
	}
	create(e) {
		this.requestOpen(null), this.pending = e;
		let t = this.composer.querySelector(".nowo-milkdown-comments__quote");
		t.textContent = e ? e.quote.exact.slice(0, 200) : "", t.hidden = !e, this.composer.hidden = !1;
		let n = this.composer.querySelector("textarea");
		n.focus(), setTimeout(() => n.focus(), 0);
	}
	open(e) {
		!this.destroyed && t.test(e) && (this.requestOpen(e), this.open_ = e, this.render(), this.refresh([e]).then(() => {
			if (this.destroyed) return;
			let t = this.element.querySelector(`[data-thread-id="${e}"] .nowo-milkdown-comments__thread`);
			t?.scrollIntoView({ block: "nearest" }), (t?.querySelector("button, textarea, a[href]") ?? t)?.focus();
		}));
	}
	async refresh(e) {
		if (!e) return this.load();
		for (let n of e.filter((e) => typeof e == "string" && t.test(e))) {
			let e = await this.client.get(n);
			if (this.destroyed) return;
			e.ok ? e.value && this.adopt(e.value, !0) : e.status === 404 ? this.drop(n) : this.fail("get", e.status, "comments.load_failed");
		}
		this.render();
	}
	destroy() {
		this.destroyed = !0, this.live?.close(), this.liveTimer && clearTimeout(this.liveTimer), this.deps.root.removeEventListener("nowo-milkdown:comment-anchors-changed", this.onAnchors), this.deps.root.removeEventListener("nowo-milkdown:comment-orphaned", this.onOrphaned), this.deps.root.classList.remove("nowo-milkdown--with-comments"), this.element.remove();
	}
	fail(e, t, n) {
		r(this.deps.root, "comment-error", {
			action: e,
			status: t
		}), this.deps.announce(n, {}, !0);
	}
	drop(e) {
		this.threads.delete(e), this.page.delete(e), this.details.delete(e), this.open_ === e && (this.open_ = null), this.deps.api().remove(e);
	}
	adopt(e, t = !1) {
		let n = this.details.get(e.id);
		this.details.set(e.id, e);
		let r = {
			id: e.id,
			anchor: e.anchor,
			resolved: e.resolved,
			count: e.count,
			...e.updatedAt ? { updatedAt: e.updatedAt } : {}
		};
		(e.anchor ? this.threads : this.page).set(e.id, r), this.deps.api().upsert(r);
		let i = e.comments[e.comments.length - 1];
		t && n && i && !n.comments.some((e) => e.id === i.id) && !this.element.closest("[hidden]") && this.deps.announce("comments.new_reply", { name: i.author.name });
	}
	async submitNew(e) {
		let t = this.pending ?? null, n = await this.client.create(t, e);
		this.destroyed || (!n.ok || !n.value ? this.fail("create", n.ok ? 0 : n.status, "comments.failed") : (this.adopt(n.value), this.closeComposer(), r(this.deps.root, "comment-created", { thread: (t ? this.threads : this.page).get(n.value.id) }), this.deps.announce("comments.sent", {}), this.open(n.value.id)));
	}
	closeComposer() {
		this.pending = void 0, this.composer.reset(), this.composer.hidden = !0;
	}
	async act(e, t, n) {
		let i = await n();
		this.destroyed || (i.ok ? i.value ? this.adopt(i.value) : this.drop(t) : (i.status === 404 && this.drop(t), this.fail(e, i.status, "comments.failed")), i.ok && e === "resolve" && this.open_ === t && (this.open_ = null), r(this.deps.root, "comment-changed", {
			id: t,
			action: e
		}), this.render());
	}
	async saveAnchors(e) {
		for (let { id: t, anchor: n } of e) {
			let e = this.threads.get(t)?.updatedAt ?? null, i = await this.client.anchor(t, n, e);
			if (this.destroyed) return;
			i.ok && i.value ? this.adopt(i.value) : !i.ok && i.status === 409 ? await this.refresh([t]) : i.ok || r(this.deps.root, "comment-error", {
				action: "anchor",
				status: i.status
			});
		}
	}
	connectLive() {
		let { options: e, hubUrl: n } = this.deps;
		if (!e.topic || !n) return;
		let r = new URL(n, window.location.href);
		r.searchParams.append("topic", e.topic), this.live = this.deps.eventSource ? this.deps.eventSource(r.toString()) : new EventSource(r.toString(), { withCredentials: !0 }), this.live.addEventListener("message", (e) => {
			let n;
			try {
				n = JSON.parse(String(e.data)).threads;
			} catch {
				return;
			}
			if (Array.isArray(n)) {
				for (let e of n.slice(0, 100)) typeof e == "string" && t.test(e) && this.liveIds.add(e);
				this.liveIds.size && !this.liveTimer && (this.liveTimer = setTimeout(() => {
					this.liveTimer = null;
					let e = [...this.liveIds];
					this.liveIds.clear(), this.refresh(e.length > 100 ? void 0 : e);
				}, 500));
			}
		});
	}
	form(t, n, r, i, a = "", o = null) {
		let { runtime: s } = this.deps, c = E("form", "nowo-milkdown-comments__form"), l = E("blockquote", "nowo-milkdown-comments__quote");
		l.hidden = !0;
		let u = E("label", "nowo-milkdown-comments__label", t), d = E("textarea", "nowo-milkdown-comments__input");
		d.rows = 3, d.maxLength = 1e4, d.value = o === null ? a : this.drafts.get(o) ?? a, o !== null && (d.dataset.draft = o, d.addEventListener("input", () => this.drafts.set(o, d.value))), u.append(d);
		let f = E("button", "nowo-milkdown__action", n);
		f.type = "submit";
		let p = E("button", "nowo-milkdown__action", e(s, "comments.cancel"));
		p.type = "button", p.addEventListener("click", i), c.append(l, u, f, p);
		let m = () => {
			let e = d.value.trim();
			e !== "" && r(e);
		};
		return c.addEventListener("submit", (e) => {
			e.preventDefault(), m();
		}), d.addEventListener("keydown", (e) => {
			e.key === "Enter" && (e.ctrlKey || e.metaKey) ? (e.preventDefault(), m()) : e.key === "Escape" && !this.host && (e.preventDefault(), i());
		}), c;
	}
	render() {
		let e = document.activeElement instanceof HTMLTextAreaElement && this.element.contains(document.activeElement) ? document.activeElement.dataset.draft ?? null : null;
		if (this.renderLists(), e !== null) {
			let t = this.element.querySelector(`textarea[data-draft="${CSS.escape(e)}"]`);
			t?.focus(), t?.setSelectionRange(t.value.length, t.value.length);
		}
	}
	renderLists() {
		let e = this.deps.api(), t = this.deps.options.panel === "margin" ? this.deps.tops() : /* @__PURE__ */ new Map(), n = [...this.threads.values()].filter((e) => !e.resolved || this.showResolved), r = (n) => t.get(n.id) ?? (e.status(n.id) === "orphaned" ? 2 ** 53 - 1 : 0);
		n.sort((e, t) => r(e) - r(t)), this.list.replaceChildren(...n.map((t) => this.item(t, e.status(t.id) === "orphaned")));
		let i = 0;
		for (let e of this.list.children) {
			let n = t.get(e.dataset.threadId ?? "");
			if (n === void 0) continue;
			let r = Math.max(n, i);
			e.style.marginTop = `${Math.max(0, r - i)}px`, i = r + e.offsetHeight;
		}
		this.pageList.replaceChildren(...[...this.page.values()].filter((e) => !e.resolved || this.showResolved).map((e) => this.item(e, !1)));
	}
	item(t, n) {
		let { runtime: i } = this.deps, a = E("li", "nowo-milkdown-comments__item");
		a.dataset.threadId = t.id, t.resolved && a.classList.add("nowo-milkdown-comments__item--resolved");
		let o = E("button", "nowo-milkdown-comments__card");
		o.type = "button", o.setAttribute("aria-expanded", String(this.open_ === t.id));
		let s = this.details.get(t.id), c = s?.comments.find((e) => !e.deleted), l = t.anchor ? t.anchor.quote.exact.slice(0, 120) : "";
		return l && o.append(E("span", "nowo-milkdown-comments__card-quote", l)), o.append(E("span", "nowo-milkdown-comments__card-text", t.label ?? (c ? `${c.author.name}` : e(i, "comments.open")))), o.append(E("span", "nowo-milkdown-comments__count", e(i, "comments.count", { count: t.count }))), o.addEventListener("click", () => {
			this.open_ === t.id ? (this.open_ = null, this.render()) : (t.anchor && !n && this.deps.api().reveal(t.id), r(this.deps.root, "comment-open", { id: t.id }, !0) && this.open(t.id));
		}), a.append(o), n && a.append(E("p", "nowo-milkdown-comments__orphaned", e(i, "comments.orphaned"))), this.open_ === t.id && s && a.append(this.thread(s)), a;
	}
	thread(t) {
		let { runtime: n } = this.deps, r = E("section", "nowo-milkdown-comments__thread");
		r.tabIndex = -1, r.setAttribute("aria-label", e(n, "comments.thread")), r.addEventListener("keydown", (e) => {
			this.host || e.key !== "Escape" || e.target.tagName === "TEXTAREA" || (e.preventDefault(), this.open_ = null, this.render(), t.anchor && this.deps.api().status(t.id) === "attached" && this.deps.api().focus(t.id));
		});
		let i = E("ol", "nowo-milkdown-comments__comments");
		for (let e of t.comments) i.append(this.comment(t, e));
		if (r.append(i), t.resolved && t.resolvedBy) {
			let i = E("p", "nowo-milkdown-comments__resolved");
			i.append(l(t.resolvedBy.name, null, t.resolvedBy.avatarUrl), ` ${e(n, "comments.resolved_by", { name: t.resolvedBy.name })}`), r.append(i);
		}
		let a = E("div", "nowo-milkdown-comments__actions");
		if (t.can.resolve) {
			let r = E("button", "nowo-milkdown__action", e(n, t.resolved ? "comments.reopen" : "comments.resolve"));
			r.type = "button", r.addEventListener("click", () => void this.act(t.resolved ? "reopen" : "resolve", t.id, () => this.client.resolve(t.id, !t.resolved))), a.append(r);
		}
		return r.append(a), t.can.reply && r.append(this.form(e(n, "comments.reply"), e(n, "comments.send"), async (e) => {
			this.drafts.delete(t.id), await this.act("reply", t.id, () => this.client.reply(t.id, e));
		}, () => r.querySelector("textarea")?.blur(), "", t.id)), r;
	}
	comment(t, n) {
		let { runtime: r } = this.deps, i = E("li", "nowo-milkdown-comments__comment");
		if (n.deleted) return i.append(E("p", "nowo-milkdown-comments__deleted", e(r, "comments.deleted"))), i;
		let a = E("p", "nowo-milkdown-comments__meta");
		if (a.append(l(n.author.name, n.author.color, n.author.avatarUrl)), a.append(E("strong", "nowo-milkdown-comments__author", n.author.name)), n.createdAt) {
			let e = E("time", "nowo-milkdown-comments__time", j(n.createdAt));
			e.dateTime = n.createdAt, a.append(" ", e);
		}
		n.editedAt && a.append(" ", E("span", "nowo-milkdown-comments__edited", e(r, "comments.edited")));
		let o = E("div", "nowo-milkdown-comments__body nowo-md");
		if (o.append(w(n.html)), i.append(a, o), t.can.react || n.reactions.length) {
			let a = E("div", "nowo-milkdown-comments__reactions"), o = new Map(n.reactions.map((e) => [e.emoji, e])), s = t.can.react ? [.../* @__PURE__ */ new Set([...n.reactions.map((e) => e.emoji), ...T])] : [...o.keys()];
			for (let i of s) {
				let s = o.get(i), c = E("button", "nowo-milkdown-comments__reaction", s ? `${i} ${s.count}` : i);
				c.type = "button", c.setAttribute("aria-pressed", String(s?.mine === !0)), c.setAttribute("aria-label", e(r, "comments.react", {
					emoji: i,
					count: s?.count ?? 0
				})), c.disabled = !t.can.react, c.addEventListener("click", () => void this.act("react", t.id, () => this.client.react(n.id, i, s?.mine !== !0))), a.append(c);
			}
			i.append(a);
		}
		let s = E("div", "nowo-milkdown-comments__own");
		if (n.can.edit) {
			let i = E("button", "nowo-milkdown__action", e(r, "comments.edit"));
			i.type = "button", i.addEventListener("click", () => {
				let i = this.form(e(r, "comments.edit"), e(r, "comments.save"), (e) => this.act("edit", t.id, () => this.client.edit(n.id, e)), () => this.render());
				o.replaceWith(i), i.querySelector("textarea").focus();
			}), s.append(i);
		}
		if (n.can.delete) {
			let i = E("button", "nowo-milkdown__action", e(r, "comments.delete"));
			i.type = "button", i.addEventListener("click", () => void this.act("delete", t.id, () => this.client.remove(n.id))), s.append(i);
		}
		return s.childElementCount && i.append(s), i;
	}
};
function j(e) {
	let t = new Date(e);
	if (Number.isNaN(t.getTime())) return e;
	try {
		return new Intl.DateTimeFormat(document.documentElement.lang || void 0, {
			dateStyle: "medium",
			timeStyle: "short"
		}).format(t);
	} catch {
		return t.toISOString();
	}
}
function M(e) {
	let t = new A(e);
	return t.load(), t;
}
export { M as mountThreadPanel };
