import { i as e, n as t, t as n } from "./rolldown-runtime-CwxnZyOB.js";
import { n as r, t as i } from "./w3c-keyname-D9tQs9_9.js";
// node_modules/.pnpm/@milkdown+exception@7.23.0/node_modules/@milkdown/exception/lib/index.js
var a = /* @__PURE__ */ function(e) {
	return e.docTypeError = "docTypeError", e.contextNotFound = "contextNotFound", e.timerNotFound = "timerNotFound", e.ctxCallOutOfScope = "ctxCallOutOfScope", e.createNodeInParserFail = "createNodeInParserFail", e.stackOverFlow = "stackOverFlow", e.parserMatchError = "parserMatchError", e.serializerMatchError = "serializerMatchError", e.getAtomFromSchemaFail = "getAtomFromSchemaFail", e.expectDomTypeError = "expectDomTypeError", e.callCommandBeforeEditorView = "callCommandBeforeEditorView", e.missingRootElement = "missingRootElement", e.missingNodeInSchema = "missingNodeInSchema", e.missingMarkInSchema = "missingMarkInSchema", e.ctxNotBind = "ctxNotBind", e.missingYjsDoc = "missingYjsDoc", e.aiProviderError = "aiProviderError", e.aiBuildContextError = "aiBuildContextError", e;
}({}), o = class extends Error {
	constructor(e, t, n) {
		super(t, n), this.name = "MilkdownError", this.code = e, n?.cause !== void 0 && (this.cause = n.cause);
	}
}, s = (e, t) => typeof t == "function" ? "[Function]" : t, c = (e) => JSON.stringify(e, s);
function l(e) {
	return new o(a.docTypeError, `Doc type error, unsupported type: ${c(e)}`);
}
function u(e) {
	return new o(a.contextNotFound, `Context "${e}" not found, do you forget to inject it?`);
}
function d(e) {
	return new o(a.timerNotFound, `Timer "${e}" not found, do you forget to record it?`);
}
function f() {
	return new o(a.ctxCallOutOfScope, "Should not call a context out of the plugin.");
}
function p(e, t, n) {
	let r = `Cannot create node for ${"name" in e ? e.name : e}`, i = (e) => {
		if (e == null) return "null";
		if (Array.isArray(e)) return `[${e.map(i).join(", ")}]`;
		if (typeof e == "object") return "toJSON" in e && typeof e.toJSON == "function" ? JSON.stringify(e.toJSON()) : "spec" in e ? JSON.stringify(e.spec) : JSON.stringify(e);
		if (typeof e == "string" || typeof e == "number" || typeof e == "boolean") return JSON.stringify(e);
		if (typeof e == "function") return `[Function: ${e.name || "anonymous"}]`;
		try {
			return String(e);
		} catch {
			return "[Unserializable]";
		}
	}, s = [
		["[Description]", r],
		["[Attributes]", t],
		["[Content]", (n ?? []).map((e) => e ? typeof e == "object" && "type" in e ? `${e}` : i(e) : "null")]
	].reduce((e, [t, n]) => {
		let r = `${t}: ${i(n)}.`;
		return e.concat(r);
	}, []);
	return new o(a.createNodeInParserFail, s.join("\n"));
}
function m() {
	return new o(a.stackOverFlow, "Stack over flow, cannot pop on an empty stack.");
}
function h(e) {
	return new o(a.parserMatchError, `Cannot match target parser for node: ${c(e)}.`);
}
function g(e) {
	return new o(a.serializerMatchError, `Cannot match target serializer for node: ${c(e)}.`);
}
function _(e, t) {
	return new o(a.getAtomFromSchemaFail, `Cannot get ${e}: ${t} from schema.`);
}
function v(e) {
	return new o(a.expectDomTypeError, `Expect to be a dom, but get: ${c(e)}.`);
}
function y() {
	return new o(a.callCommandBeforeEditorView, "You're trying to call a command before editor view initialized, make sure to get commandManager from ctx after editor view has been initialized");
}
function b(e) {
	return new o(a.missingNodeInSchema, `Missing node in schema, milkdown cannot find "${e}" in schema.`);
}
function x(e) {
	return new o(a.missingMarkInSchema, `Missing mark in schema, milkdown cannot find "${e}" in schema.`);
}
function ee() {
	return new o(a.ctxNotBind, "Context not bind, please make sure the plugin has been initialized.");
}
function te() {
	return new o(a.missingYjsDoc, "Missing yjs doc, please make sure you have bind one.");
}
function ne(e) {
	try {
		return c(e);
	} catch {
		return "[Unserializable]";
	}
}
function re(e) {
	let t = e instanceof Error ? e.message : ne(e);
	return new o(a.aiProviderError, `AI provider error: ${t}`, { cause: e });
}
function S(e) {
	let t = e instanceof Error ? e.message : ne(e);
	return new o(a.aiBuildContextError, `AI buildContext failed: ${t}`, { cause: e });
}
// node_modules/.pnpm/@milkdown+ctx@7.23.0/node_modules/@milkdown/ctx/lib/index.js
var C = class {
	constructor() {
		this.sliceMap = /* @__PURE__ */ new Map(), this.get = (e) => {
			let t = typeof e == "string" ? [...this.sliceMap.values()].find((t) => t.type.name === e) : this.sliceMap.get(e.id);
			if (!t) throw u(typeof e == "string" ? e : e.name);
			return t;
		}, this.remove = (e) => {
			let t = typeof e == "string" ? [...this.sliceMap.values()].find((t) => t.type.name === e) : this.sliceMap.get(e.id);
			t && this.sliceMap.delete(t.type.id);
		}, this.has = (e) => typeof e == "string" ? [...this.sliceMap.values()].some((t) => t.type.name === e) : this.sliceMap.has(e.id);
	}
}, w = class {
	#e;
	#t;
	#n;
	constructor(e, t, n) {
		this.#e = [], this.#n = () => {
			this.#e.forEach((e) => e(this.#t));
		}, this.set = (e) => {
			this.#t = e, this.#n();
		}, this.get = () => this.#t, this.update = (e) => {
			this.#t = e(this.#t), this.#n();
		}, this.type = n, this.#t = t, e.set(n.id, this);
	}
	on(e) {
		return this.#e.push(e), () => {
			this.#e = this.#e.filter((t) => t !== e);
		};
	}
	once(e) {
		let t = this.on((n) => {
			e(n), t();
		});
		return t;
	}
	off(e) {
		this.#e = this.#e.filter((t) => t !== e);
	}
	offAll() {
		this.#e = [];
	}
}, ie = class {
	constructor(e, t) {
		this.id = Symbol(`Context-${t}`), this.name = t, this._defaultValue = e, this._typeInfo = () => {
			throw f();
		};
	}
	create(e, t = this._defaultValue) {
		return new w(e, t, this);
	}
}, T = (e, t) => new ie(e, t), ae = class {
	#e;
	#t;
	#n;
	#r;
	#i;
	#a;
	#o;
	constructor(e, t, n) {
		this.#r = /* @__PURE__ */ new Set(), this.#i = /* @__PURE__ */ new Set(), this.#a = /* @__PURE__ */ new Map(), this.#o = /* @__PURE__ */ new Map(), this.read = () => ({
			metadata: this.#e,
			injectedSlices: [...this.#r].map((e) => ({
				name: typeof e == "string" ? e : e.name,
				value: this.#s(e)
			})),
			consumedSlices: [...this.#i].map((e) => ({
				name: typeof e == "string" ? e : e.name,
				value: this.#s(e)
			})),
			recordedTimers: [...this.#a].map(([e, { duration: t }]) => ({
				name: e.name,
				duration: t,
				status: this.#c(e)
			})),
			waitTimers: [...this.#o].map(([e, { duration: t }]) => ({
				name: e.name,
				duration: t,
				status: this.#c(e)
			}))
		}), this.onRecord = (e) => {
			this.#a.set(e, {
				start: Date.now(),
				duration: 0
			});
		}, this.onClear = (e) => {
			this.#a.delete(e);
		}, this.onDone = (e) => {
			let t = this.#a.get(e);
			t && (t.duration = Date.now() - t.start);
		}, this.onWait = (e, t) => {
			let n = Date.now();
			t.finally(() => {
				this.#o.set(e, { duration: Date.now() - n });
			}).catch(console.error);
		}, this.onInject = (e) => {
			this.#r.add(e);
		}, this.onRemove = (e) => {
			this.#r.delete(e);
		}, this.onUse = (e) => {
			this.#i.add(e);
		}, this.#s = (e) => this.#t.get(e).get(), this.#c = (e) => this.#n.get(e).status, this.#t = e, this.#n = t, this.#e = n;
	}
	#s;
	#c;
}, oe = class e {
	#e;
	#t;
	#n;
	#r;
	constructor(t, n, r) {
		this.produce = (t) => t && Object.keys(t).length ? new e(this.#e, this.#t, { ...t }) : this, this.inject = (e, t) => {
			let n = e.create(this.#e.sliceMap);
			return t != null && n.set(t), this.#r?.onInject(e), this;
		}, this.remove = (e) => (this.#e.remove(e), this.#r?.onRemove(e), this), this.record = (e) => (e.create(this.#t.store), this.#r?.onRecord(e), this), this.clearTimer = (e) => (this.#t.remove(e), this.#r?.onClear(e), this), this.isInjected = (e) => this.#e.has(e), this.isRecorded = (e) => this.#t.has(e), this.use = (e) => (this.#r?.onUse(e), this.#e.get(e)), this.get = (e) => this.use(e).get(), this.set = (e, t) => this.use(e).set(t), this.update = (e, t) => this.use(e).update(t), this.timer = (e) => this.#t.get(e), this.done = (e) => {
			this.timer(e).done(), this.#r?.onDone(e);
		}, this.wait = (e) => {
			let t = this.timer(e).start();
			return this.#r?.onWait(e, t), t;
		}, this.waitTimers = async (e) => {
			await Promise.all(this.get(e).map((e) => this.wait(e)));
		}, this.#e = t, this.#t = n, this.#n = r, r && (this.#r = new ae(t, n, r));
	}
	get meta() {
		return this.#n;
	}
	get inspector() {
		return this.#r;
	}
}, se = class {
	constructor() {
		this.store = /* @__PURE__ */ new Map(), this.get = (e) => {
			let t = this.store.get(e.id);
			if (!t) throw d(e.name);
			return t;
		}, this.remove = (e) => {
			this.store.delete(e.id);
		}, this.has = (e) => this.store.has(e.id);
	}
}, ce = class {
	#e;
	#t;
	#n;
	#r;
	#i;
	constructor(e, t) {
		this.#e = null, this.#t = null, this.#r = "pending", this.#i = null, this.start = () => (this.#e ??= new Promise((e, t) => {
			this.#t = (t) => {
				t instanceof CustomEvent && t.detail.id === this.#n && (this.#r = "resolved", this.#a(), t.stopImmediatePropagation(), e());
			}, this.#o(() => {
				this.#r === "pending" && (this.#r = "rejected"), this.#a(), t(/* @__PURE__ */ Error(`Timing ${this.type.name} timeout.`));
			}), this.#r = "pending", addEventListener(this.type.name, this.#t);
		}), this.#e), this.done = () => {
			let e = new CustomEvent(this.type.name, { detail: { id: this.#n } });
			dispatchEvent(e);
		}, this.#a = () => {
			this.#i !== null && (clearTimeout(this.#i), this.#i = null), this.#t && removeEventListener(this.type.name, this.#t);
		}, this.#o = (e) => {
			this.#i = setTimeout(() => {
				this.#i = null, e();
			}, this.type.timeout);
		}, this.#n = Symbol(t.name), this.type = t, e.set(t.id, this);
	}
	get status() {
		return this.#r;
	}
	#a;
	#o;
}, le = class {
	constructor(e, t = 3e3) {
		this.create = (e) => new ce(e, this), this.id = Symbol(`Timer-${e}`), this.name = e, this.timeout = t;
	}
}, E = (e, t = 3e3) => new le(e, t), ue = {};
function de(e, t) {
	let n = t || ue;
	return fe(e, typeof n.includeImageAlt != "boolean" || n.includeImageAlt, typeof n.includeHtml != "boolean" || n.includeHtml);
}
function fe(e, t, n) {
	if (me(e)) {
		if ("value" in e) return e.type === "html" && !n ? "" : e.value;
		if (t && "alt" in e && e.alt) return e.alt;
		if ("children" in e) return pe(e.children, t, n);
	}
	return Array.isArray(e) ? pe(e, t, n) : "";
}
function pe(e, t, n) {
	let r = [], i = -1;
	for (; ++i < e.length;) r[i] = fe(e[i], t, n);
	return r.join("");
}
function me(e) {
	return !!(e && typeof e == "object");
}
// node_modules/.pnpm/decode-named-character-reference@1.3.0/node_modules/decode-named-character-reference/index.dom.js
var he = document.createElement("i");
function ge(e) {
	let t = "&" + e + ";";
	he.innerHTML = t;
	let n = he.textContent;
	return n.charCodeAt(n.length - 1) === 59 && e !== "semi" ? !1 : n !== t && n;
}
// node_modules/.pnpm/micromark-util-chunked@2.0.1/node_modules/micromark-util-chunked/index.js
function _e(e, t, n, r) {
	let i = e.length, a = 0, o;
	if (t = t < 0 ? -t > i ? 0 : i + t : t > i ? i : t, n = n > 0 ? n : 0, r.length < 1e4) o = Array.from(r), o.unshift(t, n), e.splice(...o);
	else for (n && e.splice(t, n); a < r.length;) o = r.slice(a, a + 1e4), o.unshift(t, 0), e.splice(...o), a += 1e4, t += 1e4;
}
function ve(e, t) {
	return e.length > 0 ? (_e(e, e.length, 0, t), e) : t;
}
// node_modules/.pnpm/micromark-util-combine-extensions@2.0.1/node_modules/micromark-util-combine-extensions/index.js
var ye = {}.hasOwnProperty;
function be(e) {
	let t = {}, n = -1;
	for (; ++n < e.length;) xe(t, e[n]);
	return t;
}
function xe(e, t) {
	let n;
	for (n in t) {
		let r = (ye.call(e, n) ? e[n] : void 0) || (e[n] = {}), i = t[n], a;
		if (i) for (a in i) {
			ye.call(r, a) || (r[a] = []);
			let e = i[a];
			Se(r[a], Array.isArray(e) ? e : e ? [e] : []);
		}
	}
}
function Se(e, t) {
	let n = -1, r = [];
	for (; ++n < t.length;) (t[n].add === "after" ? e : r).push(t[n]);
	_e(e, 0, 0, r);
}
// node_modules/.pnpm/micromark-util-decode-numeric-character-reference@2.0.2/node_modules/micromark-util-decode-numeric-character-reference/index.js
function Ce(e, t) {
	let n = Number.parseInt(e, t);
	return n < 9 || n === 11 || n > 13 && n < 32 || n > 126 && n < 160 || n > 55295 && n < 57344 || n > 64975 && n < 65008 || (n & 65535) == 65535 || (n & 65535) == 65534 || n > 1114111 ? "�" : String.fromCodePoint(n);
}
// node_modules/.pnpm/micromark-util-normalize-identifier@2.0.1/node_modules/micromark-util-normalize-identifier/index.js
function we(e) {
	return e.replace(/[\t\n\r ]+/g, " ").replace(/^ | $/g, "").toLowerCase().toUpperCase();
}
// node_modules/.pnpm/micromark-util-character@2.1.1/node_modules/micromark-util-character/index.js
var D = Me(/[A-Za-z]/), O = Me(/[\dA-Za-z]/), Te = Me(/[#-'*+\--9=?A-Z^-~]/);
function Ee(e) {
	return e !== null && (e < 32 || e === 127);
}
var De = Me(/\d/), Oe = Me(/[\dA-Fa-f]/), ke = Me(/[!-/:-@[-`{-~]/);
function k(e) {
	return e !== null && e < -2;
}
function A(e) {
	return e !== null && (e < 0 || e === 32);
}
function j(e) {
	return e === -2 || e === -1 || e === 32;
}
var Ae = Me(/\p{P}|\p{S}/u), je = Me(/\s/);
function Me(e) {
	return t;
	function t(t) {
		return t !== null && t > -1 && e.test(String.fromCharCode(t));
	}
}
// node_modules/.pnpm/micromark-factory-space@2.1.0/node_modules/micromark-factory-space/index.js
function M(e, t, n, r) {
	let i = r ? r - 1 : Infinity, a = 0;
	return o;
	function o(r) {
		return j(r) ? (e.enter(n), s(r)) : t(r);
	}
	function s(r) {
		return j(r) && a++ < i ? (e.consume(r), s) : (e.exit(n), t(r));
	}
}
function Ne(e, t, n, r, i, a) {
	let o = 0;
	return s;
	function s(t) {
		return a > 0 && j(t) ? (e.enter(r), c(t)) : l(t);
	}
	function c(t) {
		return j(t) && o < a ? (e.consume(t), o++, c) : (e.exit(r), l(t));
	}
	function l(e) {
		return o >= i ? t(e) : n(e);
	}
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/initialize/content.js
var Pe = { tokenize: Fe };
function Fe(e) {
	let t = e.attempt(this.parser.constructs.contentInitial, r, i), n;
	return t;
	function r(n) {
		if (n === null) e.consume(n);
		else return e.enter("lineEnding"), e.consume(n), e.exit("lineEnding"), M(e, t, "linePrefix");
	}
	function i(t) {
		return e.enter("paragraph"), a(t);
	}
	function a(t) {
		let r = e.enter("chunkText", {
			contentType: "text",
			previous: n
		});
		return n && (n.next = r), n = r, o(t);
	}
	function o(t) {
		if (t === null) e.exit("chunkText"), e.exit("paragraph"), e.consume(t);
		else return k(t) ? (e.consume(t), e.exit("chunkText"), a) : (e.consume(t), o);
	}
}
// node_modules/.pnpm/micromark-util-edit-map@1.0.0/node_modules/micromark-util-edit-map/index.js
var Ie = class {
	constructor() {
		this.index = /* @__PURE__ */ new Map(), this.map = [];
	}
	add(e, t, n) {
		Le(this, e, t, n, !1);
	}
	addBefore(e, t, n) {
		Le(this, e, t, n, !0);
	}
	consume(e) {
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
function Le(e, t, n, r, i) {
	if (n === 0 && r.length === 0) return;
	let a = e.index.get(t);
	if (a) {
		a[1] += n, i ? (r.push(...a[2]), a[2] = r) : a[2].push(...r);
		return;
	}
	let o = [
		t,
		n,
		r
	];
	e.map.push(o), e.index.set(t, o);
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/initialize/document.js
var Re = { tokenize: Be }, ze = { tokenize: Ve };
function Be(e) {
	let t = this, n = [], r = 0, i, a, o;
	return s;
	function s(i) {
		if (r < n.length) {
			let a = n[r];
			return t.containerState = a[1], e.attempt(a[0].continuation, c, l)(i);
		}
		return l(i);
	}
	function c(e) {
		if (r++, t.containerState._closeFlow) {
			t.containerState._closeFlow = void 0, i && v();
			let n = t.events.length, a = n, o;
			for (; a--;) if (t.events[a][0] === "exit" && t.events[a][1].type === "chunkFlow") {
				o = t.events[a][1].end;
				break;
			}
			_(r);
			let s = n;
			for (; s < t.events.length;) t.events[s][1].end = { ...o }, s++;
			let c = new Ie();
			return c.add(a + 1, 0, t.events.slice(n)), c.add(n, s - n, []), c.consume(t.events), l(e);
		}
		return s(e);
	}
	function l(a) {
		if (r === n.length) {
			if (!i) return f(a);
			if (i.currentConstruct && i.currentConstruct.concrete) return m(a);
			t.interrupt = !(!i.currentConstruct || i._gfmTableDynamicInterruptHack);
		}
		return t.containerState = {}, e.check(ze, u, d)(a);
	}
	function u(e) {
		return i && v(), _(r), f(e);
	}
	function d(e) {
		return t.parser.lazy[t.now().line] = r !== n.length, o = t.now().offset, m(e);
	}
	function f(n) {
		return t.containerState = {}, e.attempt(ze, p, m)(n);
	}
	function p(e) {
		return r++, n.push([t.currentConstruct, t.containerState]), f(e);
	}
	function m(n) {
		if (n === null) i && v(), _(0), e.consume(n);
		else return i ||= t.parser.flow(t.now()), e.enter("chunkFlow", {
			_tokenizer: i,
			contentType: "flow",
			previous: a
		}), h(n);
	}
	function h(n) {
		if (n === null) g(e.exit("chunkFlow"), !0), _(0), e.consume(n);
		else return k(n) ? (e.consume(n), g(e.exit("chunkFlow")), r = 0, t.interrupt = void 0, s) : (e.consume(n), h);
	}
	function g(e, n) {
		let s = t.sliceStream(e);
		if (n && s.push(null), e.previous = a, a && (a.next = e), a = e, i.defineSkip(e.start), i.write(s), t.parser.lazy[e.start.line]) {
			let e = i.events.length;
			for (; e--;) if (i.events[e][1].start.offset < o && (!i.events[e][1].end || i.events[e][1].end.offset > o)) return;
			let n = t.events.length, a = n, s, c;
			for (; a--;) if (t.events[a][0] === "exit" && t.events[a][1].type === "chunkFlow") {
				if (s) {
					c = t.events[a][1].end;
					break;
				}
				s = !0;
			}
			for (_(r), e = n; e < t.events.length;) t.events[e][1].end = { ...c }, e++;
			let l = new Ie();
			l.add(a + 1, 0, t.events.slice(n)), l.add(n, e - n, []), l.consume(t.events);
		}
	}
	function _(r) {
		let i = n.length;
		for (; i-- > r;) {
			let r = n[i];
			t.containerState = r[1], r[0].exit.call(t, e);
		}
		n.length = r;
	}
	function v() {
		i.write([null]), a = void 0, i = void 0, t.containerState._closeFlow = void 0;
	}
}
function Ve(e, t, n) {
	return M(e, e.attempt(this.parser.constructs.document, t, n), "linePrefix", this.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4);
}
// node_modules/.pnpm/micromark-util-classify-character@2.0.1/node_modules/micromark-util-classify-character/index.js
function He(e) {
	if (e === null || A(e) || je(e)) return 1;
	if (Ae(e)) return 2;
}
// node_modules/.pnpm/micromark-util-resolve-all@2.0.1/node_modules/micromark-util-resolve-all/index.js
function Ue(e, t, n) {
	let r = [], i = -1;
	for (; ++i < e.length;) {
		let a = e[i].resolveAll;
		a && !r.includes(a) && (t = a(t, n), r.push(a));
	}
	return t;
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/attention.js
var We = {
	name: "attention",
	resolveAll: Ge,
	tokenize: Ke
};
function Ge(e, t) {
	let n = -1, r;
	for (; ++n < e.length;) if (e[n][0] === "enter" && e[n][1].type === "attentionSequence" && e[n][1]._close) {
		let i = n;
		for (; i--;) if (e[i][0] === "exit" && e[i][1].type === "attentionSequence" && e[i][1]._open && t.sliceSerialize(e[i][1]).charCodeAt(0) === t.sliceSerialize(e[n][1]).charCodeAt(0)) {
			if ((e[i][1]._close || e[n][1]._open) && (e[n][1].end.offset - e[n][1].start.offset) % 3 && !((e[i][1].end.offset - e[i][1].start.offset + e[n][1].end.offset - e[n][1].start.offset) % 3)) continue;
			let a = e[i][1].end.offset - e[i][1].start.offset > 1 && e[n][1].end.offset - e[n][1].start.offset > 1 ? 2 : 1, o = { ...e[i][1].end }, s = { ...e[n][1].start };
			qe(o, -a), qe(s, a);
			let c = {
				type: a > 1 ? "strongSequence" : "emphasisSequence",
				start: o,
				end: { ...e[i][1].end }
			}, l = {
				type: a > 1 ? "strongSequence" : "emphasisSequence",
				start: { ...e[n][1].start },
				end: s
			}, u = {
				type: a > 1 ? "strongText" : "emphasisText",
				start: { ...e[i][1].end },
				end: { ...e[n][1].start }
			}, d = {
				type: a > 1 ? "strong" : "emphasis",
				start: { ...c.start },
				end: { ...l.end }
			};
			e[i][1].end = { ...c.start }, e[n][1].start = { ...l.end }, r = [], e[i][1].end.offset - e[i][1].start.offset && (r = ve(r, [[
				"enter",
				e[i][1],
				t
			], [
				"exit",
				e[i][1],
				t
			]])), r = ve(r, [
				[
					"enter",
					d,
					t
				],
				[
					"enter",
					c,
					t
				],
				[
					"exit",
					c,
					t
				],
				[
					"enter",
					u,
					t
				]
			]), r = ve(r, Ue(t.parser.constructs.insideSpan.null, e.slice(i + 1, n), t)), r = ve(r, [
				[
					"exit",
					u,
					t
				],
				[
					"enter",
					l,
					t
				],
				[
					"exit",
					l,
					t
				],
				[
					"exit",
					d,
					t
				]
			]);
			let f = 0;
			e[n][1].end.offset - e[n][1].start.offset && (f = 2, r = ve(r, [[
				"enter",
				e[n][1],
				t
			], [
				"exit",
				e[n][1],
				t
			]])), _e(e, i - 1, n - i + 3, r), n = i + r.length - f - 2;
			break;
		}
	}
	for (n = -1; ++n < e.length;) e[n][1].type === "attentionSequence" && (e[n][1].type = "data");
	return e;
}
function Ke(e, t) {
	let n = this.parser.constructs.attentionMarkers.null, r = this.previous, i = He(r), a;
	return o;
	function o(t) {
		return a = t, e.enter("attentionSequence"), s(t);
	}
	function s(o) {
		if (o === a) return e.consume(o), s;
		let c = e.exit("attentionSequence"), l = He(o), u = !l || l === 2 && i || n.includes(o) && o !== 42 && o !== 95, d = !i || i === 2 && l || n.includes(r) && r !== 42 && r !== 95;
		return c._open = !!(a === 42 ? u : u && (i || !d)), c._close = !!(a === 42 ? d : d && (l || !u)), t(o);
	}
}
function qe(e, t) {
	e.column += t, e.offset += t, e._bufferIndex += t;
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/autolink.js
var Je = {
	name: "autolink",
	tokenize: Ye
};
function Ye(e, t, n) {
	let r = 0;
	return i;
	function i(t) {
		return e.enter("autolink"), e.enter("autolinkMarker"), e.consume(t), e.exit("autolinkMarker"), e.enter("autolinkProtocol"), a;
	}
	function a(t) {
		return D(t) ? (e.consume(t), o) : t === 64 ? n(t) : l(t);
	}
	function o(e) {
		return e === 43 || e === 45 || e === 46 || O(e) ? (r = 1, s(e)) : l(e);
	}
	function s(t) {
		return t === 58 ? (e.consume(t), r = 0, c) : (t === 43 || t === 45 || t === 46 || O(t)) && r++ < 32 ? (e.consume(t), s) : (r = 0, l(t));
	}
	function c(r) {
		return r === 62 ? (e.exit("autolinkProtocol"), e.enter("autolinkMarker"), e.consume(r), e.exit("autolinkMarker"), e.exit("autolink"), t) : r === null || r === 32 || r === 60 || Ee(r) ? n(r) : (e.consume(r), c);
	}
	function l(t) {
		return t === 64 ? (e.consume(t), u) : Te(t) ? (e.consume(t), l) : n(t);
	}
	function u(e) {
		return O(e) ? d(e) : n(e);
	}
	function d(n) {
		return n === 46 ? (e.consume(n), r = 0, u) : n === 62 ? (e.exit("autolinkProtocol").type = "autolinkEmail", e.enter("autolinkMarker"), e.consume(n), e.exit("autolinkMarker"), e.exit("autolink"), t) : f(n);
	}
	function f(t) {
		if ((t === 45 || O(t)) && r++ < 63) {
			let n = t === 45 ? f : d;
			return e.consume(t), n;
		}
		return n(t);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/blank-line.js
var Xe = {
	partial: !0,
	tokenize: Ze
};
function Ze(e, t, n) {
	return r;
	function r(t) {
		return j(t) ? M(e, i, "linePrefix")(t) : i(t);
	}
	function i(e) {
		return e === null || k(e) ? t(e) : n(e);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/block-quote.js
var Qe = {
	continuation: { tokenize: et },
	exit: tt,
	name: "blockQuote",
	tokenize: $e
};
function $e(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		if (t === 62) {
			let n = r.containerState;
			return n.open ||= (e.enter("blockQuote", { _container: !0 }), !0), e.enter("blockQuotePrefix"), e.enter("blockQuoteMarker"), e.consume(t), e.exit("blockQuoteMarker"), a;
		}
		return n(t);
	}
	function a(n) {
		return j(n) ? (e.enter("blockQuotePrefixWhitespace"), e.consume(n), e.exit("blockQuotePrefixWhitespace"), e.exit("blockQuotePrefix"), t) : (e.exit("blockQuotePrefix"), t(n));
	}
}
function et(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return j(t) ? M(e, a, "linePrefix", r.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4)(t) : a(t);
	}
	function a(r) {
		return e.attempt(Qe, t, n)(r);
	}
}
function tt(e) {
	e.exit("blockQuote");
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/character-escape.js
var nt = {
	name: "characterEscape",
	tokenize: rt
};
function rt(e, t, n) {
	return r;
	function r(t) {
		return e.enter("characterEscape"), e.enter("escapeMarker"), e.consume(t), e.exit("escapeMarker"), i;
	}
	function i(r) {
		return ke(r) ? (e.enter("characterEscapeValue"), e.consume(r), e.exit("characterEscapeValue"), e.exit("characterEscape"), t) : n(r);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/character-reference.js
var it = {
	name: "characterReference",
	tokenize: at
};
function at(e, t, n) {
	let r = this, i = 0, a, o;
	return s;
	function s(t) {
		return e.enter("characterReference"), e.enter("characterReferenceMarker"), e.consume(t), e.exit("characterReferenceMarker"), c;
	}
	function c(t) {
		return t === 35 ? (e.enter("characterReferenceMarkerNumeric"), e.consume(t), e.exit("characterReferenceMarkerNumeric"), l) : (e.enter("characterReferenceValue"), a = 31, o = O, u(t));
	}
	function l(t) {
		return t === 88 || t === 120 ? (e.enter("characterReferenceMarkerHexadecimal"), e.consume(t), e.exit("characterReferenceMarkerHexadecimal"), e.enter("characterReferenceValue"), a = 6, o = Oe, u) : (e.enter("characterReferenceValue"), a = 7, o = De, u(t));
	}
	function u(s) {
		if (s === 59 && i) {
			let i = e.exit("characterReferenceValue");
			return o === O && !ge(r.sliceSerialize(i)) ? n(s) : (e.enter("characterReferenceMarker"), e.consume(s), e.exit("characterReferenceMarker"), e.exit("characterReference"), t);
		}
		return o(s) && i++ < a ? (e.consume(s), u) : n(s);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/partial-non-lazy-continuation.js
var ot = {
	partial: !0,
	tokenize: st
};
function st(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return t === null ? n(t) : (e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), a);
	}
	function a(e) {
		return r.parser.lazy[r.now().line] ? n(e) : t(e);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/code-fenced.js
var ct = {
	concrete: !0,
	name: "codeFenced",
	tokenize: lt
};
function lt(e, t, n) {
	let r = this, i = {
		partial: !0,
		tokenize: x
	}, a = 0, o = 0, s;
	return c;
	function c(e) {
		return l(e);
	}
	function l(t) {
		let n = r.events[r.events.length - 1];
		return a = n && n[1].type === "linePrefix" ? n[2].sliceSerialize(n[1], !0).length : 0, s = t, e.enter("codeFenced"), e.enter("codeFencedFence"), e.enter("codeFencedFenceSequence"), u(t);
	}
	function u(t) {
		return t === s ? (o++, e.consume(t), u) : o < 3 ? n(t) : (e.exit("codeFencedFenceSequence"), j(t) ? M(e, d, "whitespace")(t) : d(t));
	}
	function d(n) {
		return n === null || k(n) ? (e.exit("codeFencedFence"), r.interrupt ? t(n) : e.check(ot, h, b)(n)) : (e.enter("codeFencedFenceInfo"), e.enter("chunkString", { contentType: "string" }), f(n));
	}
	function f(t) {
		return t === null || k(t) ? (e.exit("chunkString"), e.exit("codeFencedFenceInfo"), d(t)) : j(t) ? (e.exit("chunkString"), e.exit("codeFencedFenceInfo"), M(e, p, "whitespace")(t)) : t === 96 && t === s ? n(t) : (e.consume(t), f);
	}
	function p(t) {
		return t === null || k(t) ? d(t) : (e.enter("codeFencedFenceMeta"), e.enter("chunkString", { contentType: "string" }), m(t));
	}
	function m(t) {
		return t === null || k(t) ? (e.exit("chunkString"), e.exit("codeFencedFenceMeta"), d(t)) : t === 96 && t === s ? n(t) : (e.consume(t), m);
	}
	function h(t) {
		return e.attempt(i, b, g)(t);
	}
	function g(t) {
		return e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), _;
	}
	function _(t) {
		return a > 0 && j(t) ? M(e, v, "linePrefix", a + 1)(t) : v(t);
	}
	function v(t) {
		return t === null || k(t) ? e.check(ot, h, b)(t) : (e.enter("codeFlowValue"), y(t));
	}
	function y(t) {
		return t === null || k(t) ? (e.exit("codeFlowValue"), v(t)) : (e.consume(t), y);
	}
	function b(n) {
		return e.exit("codeFenced"), t(n);
	}
	function x(e, t, n) {
		let i = 0;
		return a;
		function a(t) {
			return e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), c;
		}
		function c(t) {
			return e.enter("codeFencedFence"), j(t) ? M(e, l, "linePrefix", r.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4)(t) : l(t);
		}
		function l(t) {
			return t === s ? (e.enter("codeFencedFenceSequence"), u(t)) : n(t);
		}
		function u(t) {
			return t === s ? (i++, e.consume(t), u) : i >= o ? (e.exit("codeFencedFenceSequence"), j(t) ? M(e, d, "whitespace")(t) : d(t)) : n(t);
		}
		function d(r) {
			return r === null || k(r) ? (e.exit("codeFencedFence"), t(r)) : n(r);
		}
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/code-indented.js
var ut = {
	name: "codeIndented",
	tokenize: ft
}, dt = {
	partial: !0,
	tokenize: pt
};
function ft(e, t, n) {
	return r;
	function r(t) {
		return e.enter("codeIndented"), Ne(e, i, n, "linePrefix", 4, 4)(t);
	}
	function i(t) {
		return t === null ? o(t) : k(t) ? e.attempt(dt, i, o)(t) : (e.enter("codeFlowValue"), a(t));
	}
	function a(t) {
		return t === null || k(t) ? (e.exit("codeFlowValue"), i(t)) : (e.consume(t), a);
	}
	function o(n) {
		return e.exit("codeIndented"), t(n);
	}
}
function pt(e, t, n) {
	let r = this;
	return i;
	function i(o) {
		return r.parser.lazy[r.now().line] ? n(o) : k(o) ? (e.enter("lineEnding"), e.consume(o), e.exit("lineEnding"), i) : Ne(e, t, a, "linePrefix", 4, 4)(o);
	}
	function a(e) {
		return k(e) ? i(e) : n(e);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/code-text.js
var mt = {
	name: "codeText",
	previous: gt,
	resolve: ht,
	tokenize: _t
};
function ht(e) {
	let t = e.length - 4, n = 3, r, i;
	if ((e[n][1].type === "lineEnding" || e[n][1].type === "space") && (e[t][1].type === "lineEnding" || e[t][1].type === "space")) {
		for (r = n; ++r < t;) if (e[r][1].type === "codeTextData") {
			e[n][1].type = "codeTextPadding", e[t][1].type = "codeTextPadding", n += 2, t -= 2;
			break;
		}
	}
	for (r = n - 1, t++; ++r <= t;) i === void 0 ? r !== t && e[r][1].type !== "lineEnding" && (i = r) : (r === t || e[r][1].type === "lineEnding") && (e[i][1].type = "codeTextData", r !== i + 2 && (e[i][1].end = e[r - 1][1].end, e.splice(i + 2, r - i - 2), t -= r - i - 2, r = i + 2), i = void 0);
	return e;
}
function gt(e) {
	return e !== 96 || this.events[this.events.length - 1][1].type === "characterEscape";
}
function _t(e, t, n) {
	let r = 0, i, a;
	return o;
	function o(t) {
		return e.enter("codeText"), e.enter("codeTextSequence"), s(t);
	}
	function s(t) {
		return t === 96 ? (e.consume(t), r++, s) : (e.exit("codeTextSequence"), c(t));
	}
	function c(t) {
		return t === null ? n(t) : t === 32 ? (e.enter("space"), e.consume(t), e.exit("space"), c) : t === 96 ? (a = e.enter("codeTextSequence"), i = 0, u(t)) : k(t) ? (e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), c) : (e.enter("codeTextData"), l(t));
	}
	function l(t) {
		return t === null || t === 32 || t === 96 || k(t) ? (e.exit("codeTextData"), c(t)) : (e.consume(t), l);
	}
	function u(n) {
		return n === 96 ? (e.consume(n), i++, u) : i === r ? (e.exit("codeTextSequence"), e.exit("codeText"), t(n)) : (a.type = "codeTextData", l(n));
	}
}
// node_modules/.pnpm/micromark-util-subtokenize@2.1.0/node_modules/micromark-util-subtokenize/lib/splice-buffer.js
var vt = class {
	constructor(e) {
		this.left = e ? [...e] : [], this.right = [];
	}
	get(e) {
		if (e < 0 || e >= this.left.length + this.right.length) throw RangeError("Cannot access index `" + e + "` in a splice buffer of size `" + (this.left.length + this.right.length) + "`");
		return e < this.left.length ? this.left[e] : this.right[this.right.length - e + this.left.length - 1];
	}
	get length() {
		return this.left.length + this.right.length;
	}
	shift() {
		return this.setCursor(0), this.right.pop();
	}
	slice(e, t) {
		let n = t ?? Infinity;
		return n < this.left.length ? this.left.slice(e, n) : e > this.left.length ? this.right.slice(this.right.length - n + this.left.length, this.right.length - e + this.left.length).reverse() : this.left.slice(e).concat(this.right.slice(this.right.length - n + this.left.length).reverse());
	}
	splice(e, t, n) {
		let r = t || 0;
		this.setCursor(Math.trunc(e));
		let i = this.right.splice(this.right.length - r, Infinity);
		return n && yt(this.left, n), i.reverse();
	}
	pop() {
		return this.setCursor(Infinity), this.left.pop();
	}
	push(e) {
		this.setCursor(Infinity), this.left.push(e);
	}
	pushMany(e) {
		this.setCursor(Infinity), yt(this.left, e);
	}
	unshift(e) {
		this.setCursor(0), this.right.push(e);
	}
	unshiftMany(e) {
		this.setCursor(0), yt(this.right, e.reverse());
	}
	setCursor(e) {
		if (!(e === this.left.length || e > this.left.length && this.right.length === 0 || e < 0 && this.left.length === 0)) {
			if (e < this.left.length) {
				let t = this.left.splice(e, Infinity);
				yt(this.right, t.reverse());
			} else {
				let t = this.right.splice(this.left.length + this.right.length - e, Infinity);
				yt(this.left, t.reverse());
			}
		}
	}
};
function yt(e, t) {
	let n = 0;
	if (t.length < 1e4) e.push(...t);
	else for (; n < t.length;) e.push(...t.slice(n, n + 1e4)), n += 1e4;
}
// node_modules/.pnpm/micromark-util-subtokenize@2.1.0/node_modules/micromark-util-subtokenize/index.js
function bt(e) {
	let t = {}, n = -1, r, i, a, o, s, c, l, u = new vt(e);
	for (; ++n < u.length;) {
		for (; n in t;) n = t[n];
		if (r = u.get(n), n && r[1].type === "chunkFlow" && u.get(n - 1)[1].type === "listItemPrefix" && (c = r[1]._tokenizer.events, a = 0, a < c.length && c[a][1].type === "lineEndingBlank" && (a += 2), a < c.length && c[a][1].type === "content")) for (; ++a < c.length && c[a][1].type !== "content";) c[a][1].type === "chunkText" && (c[a][1]._isInFirstContentOfListItem = !0, a++);
		if (r[0] === "enter") r[1].contentType && (Object.assign(t, xt(u, n)), n = t[n], l = !0);
		else if (r[1]._container) {
			for (a = n, i = void 0; a--;) if (o = u.get(a), o[1].type === "lineEnding" || o[1].type === "lineEndingBlank") o[0] === "enter" && (i && (u.get(i)[1].type = "lineEndingBlank"), o[1].type = "lineEnding", i = a);
			else if (o[1].type !== "linePrefix" && o[1].type !== "listItemIndent") break;
			i && (r[1].end = { ...u.get(i)[1].start }, s = u.slice(i, n), s.unshift(r), u.splice(i, n - i + 1, s));
		}
	}
	return _e(e, 0, Infinity, u.slice(0)), !l;
}
function xt(e, t) {
	let n = e.get(t)[1], r = e.get(t)[2], i = t - 1, a = [], o = n._tokenizer;
	o || (o = r.parser[n.contentType](n.start), n._contentTypeTextTrailing && (o._contentTypeTextTrailing = !0));
	let s = o.events, c = [], l = {}, u, d, f = -1, p = n, m = 0, h = 0, g = [h];
	for (; p;) {
		for (; e.get(++i)[1] !== p;);
		a.push(i), p._tokenizer || (u = r.sliceStream(p), p.next || u.push(null), d && o.defineSkip(p.start), p._isInFirstContentOfListItem && (o._gfmTasklistFirstContentOfListItem = !0), o.write(u), p._isInFirstContentOfListItem && (o._gfmTasklistFirstContentOfListItem = void 0)), d = p, p = p.next;
	}
	for (p = n; ++f < s.length;) s[f][0] === "exit" && s[f - 1][0] === "enter" && s[f][1].type === s[f - 1][1].type && s[f][1].start.line !== s[f][1].end.line && (h = f + 1, g.push(h), p._tokenizer = void 0, p.previous = void 0, p = p.next);
	for (o.events = [], p ? (p._tokenizer = void 0, p.previous = void 0) : g.pop(), f = g.length; f--;) {
		let t = s.slice(g[f], g[f + 1]), n = a.pop();
		c.push([n, n + t.length - 1]), e.splice(n, 2, t);
	}
	for (c.reverse(), f = -1; ++f < c.length;) l[m + c[f][0]] = m + c[f][1], m += c[f][1] - c[f][0] - 1;
	return l;
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/content.js
var St = {
	resolve: wt,
	tokenize: Tt
}, Ct = {
	partial: !0,
	tokenize: Et
};
function wt(e) {
	return bt(e), e;
}
function Tt(e, t) {
	let n;
	return r;
	function r(t) {
		return e.enter("content"), n = e.enter("chunkContent", { contentType: "content" }), i(t);
	}
	function i(t) {
		return t === null ? a(t) : k(t) ? e.check(Ct, o, a)(t) : (e.consume(t), i);
	}
	function a(n) {
		return e.exit("chunkContent"), e.exit("content"), t(n);
	}
	function o(t) {
		return e.consume(t), e.exit("chunkContent"), n.next = e.enter("chunkContent", {
			contentType: "content",
			previous: n
		}), n = n.next, i;
	}
}
function Et(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return e.exit("chunkContent"), e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), M(e, a, "linePrefix");
	}
	function a(i) {
		if (i === null || k(i)) return n(i);
		let a = r.events[r.events.length - 1];
		return !r.parser.constructs.disable.null.includes("codeIndented") && a && a[1].type === "linePrefix" && a[2].sliceSerialize(a[1], !0).length >= 4 ? t(i) : e.interrupt(r.parser.constructs.flow, n, t)(i);
	}
}
// node_modules/.pnpm/micromark-factory-destination@2.0.1/node_modules/micromark-factory-destination/index.js
function Dt(e, t, n, r, i, a, o, s, c) {
	let l = c || Infinity, u = 0;
	return d;
	function d(t) {
		return t === 60 ? (e.enter(r), e.enter(i), e.enter(a), e.consume(t), e.exit(a), f) : t === null || t === 32 || t === 41 || Ee(t) ? n(t) : (e.enter(r), e.enter(o), e.enter(s), e.enter("chunkString", { contentType: "string" }), h(t));
	}
	function f(n) {
		return n === 62 ? (e.enter(a), e.consume(n), e.exit(a), e.exit(i), e.exit(r), t) : (e.enter(s), e.enter("chunkString", { contentType: "string" }), p(n));
	}
	function p(t) {
		return t === 62 ? (e.exit("chunkString"), e.exit(s), f(t)) : t === null || t === 60 || k(t) ? n(t) : (e.consume(t), t === 92 ? m : p);
	}
	function m(t) {
		return t === 60 || t === 62 || t === 92 ? (e.consume(t), p) : p(t);
	}
	function h(i) {
		return !u && (i === null || i === 41 || A(i)) ? (e.exit("chunkString"), e.exit(s), e.exit(o), e.exit(r), t(i)) : u < l && i === 40 ? (e.consume(i), u++, h) : i === 41 ? (e.consume(i), u--, h) : i === null || i === 32 || i === 40 || Ee(i) ? n(i) : (e.consume(i), i === 92 ? g : h);
	}
	function g(t) {
		return t === 40 || t === 41 || t === 92 ? (e.consume(t), h) : h(t);
	}
}
// node_modules/.pnpm/micromark-factory-label@2.0.1/node_modules/micromark-factory-label/index.js
function Ot(e, t, n, r, i, a) {
	let o = this, s = 0, c;
	return l;
	function l(t) {
		return e.enter(r), e.enter(i), e.consume(t), e.exit(i), e.enter(a), u;
	}
	function u(l) {
		return s > 999 || l === null || l === 91 || l === 93 && !c || 
		/* c8 ignore next 3 */
		l === 94 && !s && "_hiddenFootnoteSupport" in o.parser.constructs ? n(l) : l === 93 ? (e.exit(a), e.enter(i), e.consume(l), e.exit(i), e.exit(r), t) : k(l) ? (e.enter("lineEnding"), e.consume(l), e.exit("lineEnding"), u) : (e.enter("chunkString", { contentType: "string" }), d(l));
	}
	function d(t) {
		return t === null || t === 91 || t === 93 || k(t) || s++ > 999 ? (e.exit("chunkString"), u(t)) : (e.consume(t), c ||= !j(t), t === 92 ? f : d);
	}
	function f(t) {
		return t === 91 || t === 92 || t === 93 ? (e.consume(t), s++, d) : d(t);
	}
}
// node_modules/.pnpm/micromark-factory-title@2.0.1/node_modules/micromark-factory-title/index.js
function kt(e, t, n, r, i, a) {
	let o;
	return s;
	function s(t) {
		return t === 34 || t === 39 || t === 40 ? (e.enter(r), e.enter(i), e.consume(t), e.exit(i), o = t === 40 ? 41 : t, c) : n(t);
	}
	function c(n) {
		return n === o ? (e.enter(i), e.consume(n), e.exit(i), e.exit(r), t) : (e.enter(a), l(n));
	}
	function l(t) {
		return t === o ? (e.exit(a), c(o)) : t === null ? n(t) : k(t) ? (e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), M(e, l, "linePrefix")) : (e.enter("chunkString", { contentType: "string" }), u(t));
	}
	function u(t) {
		return t === o || t === null || k(t) ? (e.exit("chunkString"), l(t)) : (e.consume(t), t === 92 ? d : u);
	}
	function d(t) {
		return t === o || t === 92 ? (e.consume(t), u) : u(t);
	}
}
// node_modules/.pnpm/micromark-factory-whitespace@2.0.1/node_modules/micromark-factory-whitespace/index.js
function At(e, t) {
	let n;
	return r;
	function r(i) {
		return k(i) ? (e.enter("lineEnding"), e.consume(i), e.exit("lineEnding"), n = !0, r) : j(i) ? M(e, r, n ? "linePrefix" : "lineSuffix")(i) : t(i);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/definition.js
var jt = {
	name: "definition",
	tokenize: Nt
}, Mt = {
	partial: !0,
	tokenize: Pt
};
function Nt(e, t, n) {
	let r = this, i;
	return a;
	function a(t) {
		return e.enter("definition"), o(t);
	}
	function o(t) {
		return Ot.call(r, e, s, n, "definitionLabel", "definitionLabelMarker", "definitionLabelString")(t);
	}
	function s(t) {
		return i = we(r.sliceSerialize(r.events[r.events.length - 1][1]).slice(1, -1)), t === 58 ? (e.enter("definitionMarker"), e.consume(t), e.exit("definitionMarker"), c) : n(t);
	}
	function c(t) {
		return A(t) ? At(e, l)(t) : l(t);
	}
	function l(t) {
		return Dt(e, u, n, "definitionDestination", "definitionDestinationLiteral", "definitionDestinationLiteralMarker", "definitionDestinationRaw", "definitionDestinationString")(t);
	}
	function u(t) {
		return e.attempt(Mt, d, d)(t);
	}
	function d(t) {
		return j(t) ? M(e, f, "whitespace")(t) : f(t);
	}
	function f(a) {
		return a === null || k(a) ? (e.exit("definition"), r.parser.defined.push(i), t(a)) : n(a);
	}
}
function Pt(e, t, n) {
	return r;
	function r(t) {
		return A(t) ? At(e, i)(t) : n(t);
	}
	function i(t) {
		return kt(e, a, n, "definitionTitle", "definitionTitleMarker", "definitionTitleString")(t);
	}
	function a(t) {
		return j(t) ? M(e, o, "whitespace")(t) : o(t);
	}
	function o(e) {
		return e === null || k(e) ? t(e) : n(e);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/hard-break-escape.js
var Ft = {
	name: "hardBreakEscape",
	tokenize: It
};
function It(e, t, n) {
	return r;
	function r(t) {
		return e.enter("hardBreakEscape"), e.consume(t), i;
	}
	function i(r) {
		return k(r) ? (e.exit("hardBreakEscape"), t(r)) : n(r);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/heading-atx.js
var Lt = {
	name: "headingAtx",
	resolve: Rt,
	tokenize: zt
};
function Rt(e, t) {
	let n = e.length - 2, r = 3;
	if (e[r][1].type === "whitespace" && (r += 2), n - 2 > r && e[n][1].type === "whitespace" && (n -= 2), e[n][1].type === "atxHeadingSequence" && (r === n - 1 || n - 4 > r && e[n - 2][1].type === "whitespace") && (n -= r + 1 === n ? 2 : 4), n > r) {
		let i = {
			type: "atxHeadingText",
			start: e[r][1].start,
			end: e[n][1].end
		}, a = {
			type: "chunkText",
			start: e[r][1].start,
			end: e[n][1].end,
			contentType: "text"
		};
		_e(e, r, n - r + 1, [
			[
				"enter",
				i,
				t
			],
			[
				"enter",
				a,
				t
			],
			[
				"exit",
				a,
				t
			],
			[
				"exit",
				i,
				t
			]
		]);
	}
	return e;
}
function zt(e, t, n) {
	let r = 0;
	return i;
	function i(t) {
		return e.enter("atxHeading"), a(t);
	}
	function a(t) {
		return e.enter("atxHeadingSequence"), o(t);
	}
	function o(t) {
		return t === 35 && r++ < 6 ? (e.consume(t), o) : t === null || A(t) ? (e.exit("atxHeadingSequence"), s(t)) : n(t);
	}
	function s(n) {
		return n === 35 ? (e.enter("atxHeadingSequence"), c(n)) : n === null || k(n) ? (e.exit("atxHeading"), t(n)) : j(n) ? M(e, s, "whitespace")(n) : (e.enter("atxHeadingText"), l(n));
	}
	function c(t) {
		return t === 35 ? (e.consume(t), c) : (e.exit("atxHeadingSequence"), s(t));
	}
	function l(t) {
		return t === null || t === 35 || A(t) ? (e.exit("atxHeadingText"), s(t)) : (e.consume(t), l);
	}
}
// node_modules/.pnpm/micromark-util-html-tag-name@2.0.1/node_modules/micromark-util-html-tag-name/index.js
var Bt = /* @__PURE__ */ "address.article.aside.base.basefont.blockquote.body.caption.center.col.colgroup.dd.details.dialog.dir.div.dl.dt.fieldset.figcaption.figure.footer.form.frame.frameset.h1.h2.h3.h4.h5.h6.head.header.hr.html.iframe.legend.li.link.main.menu.menuitem.nav.noframes.ol.optgroup.option.p.param.search.section.summary.table.tbody.td.tfoot.th.thead.title.tr.track.ul".split("."), Vt = [
	"pre",
	"script",
	"style",
	"textarea"
], Ht = {
	concrete: !0,
	name: "htmlFlow",
	resolveTo: Wt,
	tokenize: Gt
}, Ut = {
	partial: !0,
	tokenize: Kt
};
function Wt(e) {
	let t = e.length;
	for (; t-- && (e[t][0] !== "enter" || e[t][1].type !== "htmlFlow"););
	return t > 1 && e[t - 2][1].type === "linePrefix" && (e[t][1].start = e[t - 2][1].start, e[t + 1][1].start = e[t - 2][1].start, e.splice(t - 2, 2)), e;
}
function Gt(e, t, n) {
	let r = this, i, a, o, s, c;
	return l;
	function l(e) {
		return u(e);
	}
	function u(t) {
		return e.enter("htmlFlow"), e.enter("htmlFlowData"), e.consume(t), d;
	}
	function d(s) {
		return s === 33 ? (e.consume(s), f) : s === 47 ? (e.consume(s), a = !0, h) : s === 63 ? (e.consume(s), i = 3, r.interrupt ? t : E) : D(s) ? (e.consume(s), o = String.fromCharCode(s), g) : n(s);
	}
	function f(a) {
		return a === 45 ? (e.consume(a), i = 2, p) : a === 91 ? (e.consume(a), i = 5, s = 0, m) : D(a) ? (e.consume(a), i = 4, r.interrupt ? t : E) : n(a);
	}
	function p(i) {
		return i === 45 ? (e.consume(i), r.interrupt ? t : E) : n(i);
	}
	function m(i) {
		return i === "CDATA[".charCodeAt(s++) ? (e.consume(i), s === 6 ? r.interrupt ? t : w : m) : n(i);
	}
	function h(t) {
		return D(t) ? (e.consume(t), o = String.fromCharCode(t), g) : n(t);
	}
	function g(s) {
		if (s === null || s === 47 || s === 62 || A(s)) {
			let c = s === 47, l = o.toLowerCase();
			return !c && !a && Vt.includes(l) ? (i = 1, r.interrupt ? t(s) : w(s)) : Bt.includes(o.toLowerCase()) ? (i = 6, c ? (e.consume(s), _) : r.interrupt ? t(s) : w(s)) : (i = 7, r.interrupt && !r.parser.lazy[r.now().line] ? n(s) : a ? v(s) : y(s));
		}
		return s === 45 || O(s) ? (e.consume(s), o += String.fromCharCode(s), g) : n(s);
	}
	function _(i) {
		return i === 62 ? (e.consume(i), r.interrupt ? t : w) : n(i);
	}
	function v(t) {
		return j(t) ? (e.consume(t), v) : S(t);
	}
	function y(t) {
		return t === 47 ? (e.consume(t), S) : t === 58 || t === 95 || D(t) ? (e.consume(t), b) : j(t) ? (e.consume(t), y) : S(t);
	}
	function b(t) {
		return t === 45 || t === 46 || t === 58 || t === 95 || O(t) ? (e.consume(t), b) : x(t);
	}
	function x(t) {
		return t === 61 ? (e.consume(t), ee) : j(t) ? (e.consume(t), x) : y(t);
	}
	function ee(t) {
		return t === null || t === 60 || t === 61 || t === 62 || t === 96 ? n(t) : t === 34 || t === 39 ? (e.consume(t), c = t, te) : j(t) ? (e.consume(t), ee) : ne(t);
	}
	function te(t) {
		return t === c ? (e.consume(t), c = null, re) : t === null || k(t) ? n(t) : (e.consume(t), te);
	}
	function ne(t) {
		return t === null || t === 34 || t === 39 || t === 47 || t === 60 || t === 61 || t === 62 || t === 96 || A(t) ? x(t) : (e.consume(t), ne);
	}
	function re(e) {
		return e === 47 || e === 62 || j(e) ? y(e) : n(e);
	}
	function S(t) {
		return t === 62 ? (e.consume(t), C) : n(t);
	}
	function C(t) {
		return t === null || k(t) ? w(t) : j(t) ? (e.consume(t), C) : n(t);
	}
	function w(t) {
		return t === 45 && i === 2 ? (e.consume(t), oe) : t === 60 && i === 1 ? (e.consume(t), se) : t === 62 && i === 4 ? (e.consume(t), ue) : t === 63 && i === 3 ? (e.consume(t), E) : t === 93 && i === 5 ? (e.consume(t), le) : k(t) && (i === 6 || i === 7) ? (e.exit("htmlFlowData"), e.check(Ut, de, ie)(t)) : t === null || k(t) ? (e.exit("htmlFlowData"), ie(t)) : (e.consume(t), w);
	}
	function ie(t) {
		return e.check(ot, T, de)(t);
	}
	function T(t) {
		return e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), ae;
	}
	function ae(t) {
		return t === null || k(t) ? ie(t) : (e.enter("htmlFlowData"), w(t));
	}
	function oe(t) {
		return t === 45 ? (e.consume(t), E) : w(t);
	}
	function se(t) {
		return t === 47 ? (e.consume(t), o = "", ce) : w(t);
	}
	function ce(t) {
		if (t === 62) {
			let n = o.toLowerCase();
			return Vt.includes(n) ? (e.consume(t), ue) : w(t);
		}
		return D(t) && o.length < 8 ? (e.consume(t), o += String.fromCharCode(t), ce) : w(t);
	}
	function le(t) {
		return t === 93 ? (e.consume(t), E) : w(t);
	}
	function E(t) {
		return t === 62 ? (e.consume(t), ue) : t === 45 && i === 2 ? (e.consume(t), E) : w(t);
	}
	function ue(t) {
		return t === null || k(t) ? (e.exit("htmlFlowData"), de(t)) : (e.consume(t), ue);
	}
	function de(n) {
		return e.exit("htmlFlow"), t(n);
	}
}
function Kt(e, t, n) {
	return r;
	function r(r) {
		return e.enter("lineEnding"), e.consume(r), e.exit("lineEnding"), e.attempt(Xe, t, n);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/html-text.js
var qt = {
	name: "htmlText",
	tokenize: Jt
};
function Jt(e, t, n) {
	let r = this, i, a, o;
	return s;
	function s(t) {
		return e.enter("htmlText"), e.enter("htmlTextData"), e.consume(t), c;
	}
	function c(t) {
		return t === 33 ? (e.consume(t), l) : t === 47 ? (e.consume(t), x) : t === 63 ? (e.consume(t), y) : D(t) ? (e.consume(t), ne) : n(t);
	}
	function l(t) {
		return t === 45 ? (e.consume(t), u) : t === 91 ? (e.consume(t), a = 0, m) : D(t) ? (e.consume(t), v) : n(t);
	}
	function u(t) {
		return t === 45 ? (e.consume(t), p) : n(t);
	}
	function d(t) {
		return t === null ? n(t) : t === 45 ? (e.consume(t), f) : k(t) ? (o = d, se(t)) : (e.consume(t), d);
	}
	function f(t) {
		return t === 45 ? (e.consume(t), p) : d(t);
	}
	function p(e) {
		return e === 62 ? oe(e) : e === 45 ? f(e) : d(e);
	}
	function m(t) {
		return t === "CDATA[".charCodeAt(a++) ? (e.consume(t), a === 6 ? h : m) : n(t);
	}
	function h(t) {
		return t === null ? n(t) : t === 93 ? (e.consume(t), g) : k(t) ? (o = h, se(t)) : (e.consume(t), h);
	}
	function g(t) {
		return t === 93 ? (e.consume(t), _) : h(t);
	}
	function _(t) {
		return t === 62 ? oe(t) : t === 93 ? (e.consume(t), _) : h(t);
	}
	function v(t) {
		return t === null || t === 62 ? oe(t) : k(t) ? (o = v, se(t)) : (e.consume(t), v);
	}
	function y(t) {
		return t === null ? n(t) : t === 63 ? (e.consume(t), b) : k(t) ? (o = y, se(t)) : (e.consume(t), y);
	}
	function b(e) {
		return e === 62 ? oe(e) : y(e);
	}
	function x(t) {
		return D(t) ? (e.consume(t), ee) : n(t);
	}
	function ee(t) {
		return t === 45 || O(t) ? (e.consume(t), ee) : te(t);
	}
	function te(t) {
		return k(t) ? (o = te, se(t)) : j(t) ? (e.consume(t), te) : oe(t);
	}
	function ne(t) {
		return t === 45 || O(t) ? (e.consume(t), ne) : t === 47 || t === 62 || A(t) ? re(t) : n(t);
	}
	function re(t) {
		return t === 47 ? (e.consume(t), oe) : t === 58 || t === 95 || D(t) ? (e.consume(t), S) : k(t) ? (o = re, se(t)) : j(t) ? (e.consume(t), re) : oe(t);
	}
	function S(t) {
		return t === 45 || t === 46 || t === 58 || t === 95 || O(t) ? (e.consume(t), S) : C(t);
	}
	function C(t) {
		return t === 61 ? (e.consume(t), w) : k(t) ? (o = C, se(t)) : j(t) ? (e.consume(t), C) : re(t);
	}
	function w(t) {
		return t === null || t === 60 || t === 61 || t === 62 || t === 96 ? n(t) : t === 34 || t === 39 ? (e.consume(t), i = t, ie) : k(t) ? (o = w, se(t)) : j(t) ? (e.consume(t), w) : (e.consume(t), T);
	}
	function ie(t) {
		return t === i ? (e.consume(t), i = void 0, ae) : t === null ? n(t) : k(t) ? (o = ie, se(t)) : (e.consume(t), ie);
	}
	function T(t) {
		return t === null || t === 34 || t === 39 || t === 60 || t === 61 || t === 96 ? n(t) : t === 47 || t === 62 || A(t) ? re(t) : (e.consume(t), T);
	}
	function ae(e) {
		return e === 47 || e === 62 || A(e) ? re(e) : n(e);
	}
	function oe(r) {
		return r === 62 ? (e.consume(r), e.exit("htmlTextData"), e.exit("htmlText"), t) : n(r);
	}
	function se(t) {
		return e.exit("htmlTextData"), e.enter("lineEnding"), e.consume(t), e.exit("lineEnding"), ce;
	}
	function ce(t) {
		return j(t) ? M(e, le, "linePrefix", r.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4)(t) : le(t);
	}
	function le(t) {
		return e.enter("htmlTextData"), o(t);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/label-end.js
var Yt = {
	name: "labelEnd",
	resolveAll: $t,
	resolveTo: en,
	tokenize: tn
}, Xt = { tokenize: nn }, Zt = { tokenize: rn }, Qt = { tokenize: an };
function $t(e) {
	let t = -1, n = [];
	for (; ++t < e.length;) {
		let r = e[t][1];
		if (n.push(e[t]), r.type === "labelImage" || r.type === "labelLink" || r.type === "labelEnd") {
			let e = r.type === "labelImage" ? 4 : 2;
			r.type = "data", t += e;
		}
	}
	return e.length !== n.length && _e(e, 0, e.length, n), e;
}
function en(e, t) {
	let n = e.length, r = 0, i, a, o;
	for (; n--;) {
		let t = e[n][1];
		if (i) {
			if (t.type === "link" || t.type === "labelLink" && t._inactive) break;
			e[n][0] === "enter" && t.type === "labelLink" && (t._inactive = !0);
		} else if (a) {
			if (e[n][0] === "enter" && (t.type === "labelImage" || t.type === "labelLink") && !t._balanced && (i = n, t.type !== "labelLink")) {
				r = 2;
				break;
			}
		} else t.type === "labelEnd" && (a = n);
	}
	let s = {
		type: e[i][1].type === "labelLink" ? "link" : "image",
		start: { ...e[i][1].start },
		end: { ...e[e.length - 1][1].end }
	}, c = {
		type: "label",
		start: { ...e[i][1].start },
		end: { ...e[a][1].end }
	}, l = {
		type: "labelText",
		start: { ...e[i + r + 2][1].end },
		end: { ...e[a - 2][1].start }
	};
	return o = [[
		"enter",
		s,
		t
	], [
		"enter",
		c,
		t
	]], o = ve(o, e.slice(i + 1, i + r + 3)), o = ve(o, [[
		"enter",
		l,
		t
	]]), o = ve(o, Ue(t.parser.constructs.insideSpan.null, e.slice(i + r + 4, a - 3), t)), o = ve(o, [
		[
			"exit",
			l,
			t
		],
		e[a - 2],
		e[a - 1],
		[
			"exit",
			c,
			t
		]
	]), o = ve(o, e.slice(a + 1)), o = ve(o, [[
		"exit",
		s,
		t
	]]), _e(e, i, e.length, o), e;
}
function tn(e, t, n) {
	let r = this, i = r._labelStarts, a, o;
	if (i) {
		for (; i.length > 0 && i[i.length - 1]._balanced;) i.pop();
		a = i[i.length - 1];
	}
	return s;
	function s(t) {
		return a ? a._inactive ? d(t) : (o = r.parser.defined.includes(we(r.sliceSerialize({
			start: a.end,
			end: r.now()
		}))), e.enter("labelEnd"), e.enter("labelMarker"), e.consume(t), e.exit("labelMarker"), e.exit("labelEnd"), c) : n(t);
	}
	function c(t) {
		return t === 40 ? e.attempt(Xt, u, o ? u : d)(t) : t === 91 ? e.attempt(Zt, u, o ? l : d)(t) : o ? u(t) : d(t);
	}
	function l(t) {
		return e.attempt(Qt, u, d)(t);
	}
	function u(e) {
		return i.pop(), t(e);
	}
	function d(e) {
		return a._balanced = !0, n(e);
	}
}
function nn(e, t, n) {
	return r;
	function r(t) {
		return e.enter("resource"), e.enter("resourceMarker"), e.consume(t), e.exit("resourceMarker"), i;
	}
	function i(t) {
		return A(t) ? At(e, a)(t) : a(t);
	}
	function a(t) {
		return t === 41 ? u(t) : Dt(e, o, s, "resourceDestination", "resourceDestinationLiteral", "resourceDestinationLiteralMarker", "resourceDestinationRaw", "resourceDestinationString", 32)(t);
	}
	function o(t) {
		return A(t) ? At(e, c)(t) : u(t);
	}
	function s(e) {
		return n(e);
	}
	function c(t) {
		return t === 34 || t === 39 || t === 40 ? kt(e, l, n, "resourceTitle", "resourceTitleMarker", "resourceTitleString")(t) : u(t);
	}
	function l(t) {
		return A(t) ? At(e, u)(t) : u(t);
	}
	function u(r) {
		return r === 41 ? (e.enter("resourceMarker"), e.consume(r), e.exit("resourceMarker"), e.exit("resource"), t) : n(r);
	}
}
function rn(e, t, n) {
	let r = this;
	return i;
	function i(t) {
		return Ot.call(r, e, a, o, "reference", "referenceMarker", "referenceString")(t);
	}
	function a(e) {
		return r.parser.defined.includes(we(r.sliceSerialize(r.events[r.events.length - 1][1]).slice(1, -1))) ? t(e) : n(e);
	}
	function o(e) {
		return n(e);
	}
}
function an(e, t, n) {
	return r;
	function r(t) {
		return e.enter("reference"), e.enter("referenceMarker"), e.consume(t), e.exit("referenceMarker"), i;
	}
	function i(r) {
		return r === 93 ? (e.enter("referenceMarker"), e.consume(r), e.exit("referenceMarker"), e.exit("reference"), t) : n(r);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/label-start-image.js
var on = {
	name: "labelStartImage",
	resolveAll: Yt.resolveAll,
	tokenize: sn
};
function sn(e, t, n) {
	let r = this, i;
	return a;
	function a(t) {
		return e.enter("labelImage"), e.enter("labelImageMarker"), e.consume(t), e.exit("labelImageMarker"), o;
	}
	function o(t) {
		return t === 91 ? (e.enter("labelMarker"), e.consume(t), e.exit("labelMarker"), i = e.exit("labelImage"), s) : n(t);
	}
	function s(e) {
		return e === 94 && "_hiddenFootnoteSupport" in r.parser.constructs ? n(e) : (r._labelStarts = r._labelStarts || [], r._labelStarts.push(i), t(e));
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/label-start-link.js
var cn = {
	name: "labelStartLink",
	resolveAll: Yt.resolveAll,
	tokenize: ln
};
function ln(e, t, n) {
	let r = this, i;
	return a;
	function a(t) {
		return e.enter("labelLink"), e.enter("labelMarker"), e.consume(t), e.exit("labelMarker"), i = e.exit("labelLink"), o;
	}
	function o(e) {
		return e === 94 && "_hiddenFootnoteSupport" in r.parser.constructs ? n(e) : (r._labelStarts = r._labelStarts || [], r._labelStarts.push(i), t(e));
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/line-ending.js
var un = {
	name: "lineEnding",
	tokenize: dn
};
function dn(e, t) {
	return n;
	function n(n) {
		return e.enter("lineEnding"), e.consume(n), e.exit("lineEnding"), M(e, t, "linePrefix");
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/thematic-break.js
var fn = {
	name: "thematicBreak",
	tokenize: pn
};
function pn(e, t, n) {
	let r = 0, i;
	return a;
	function a(t) {
		return e.enter("thematicBreak"), o(t);
	}
	function o(e) {
		return i = e, s(e);
	}
	function s(a) {
		return a === i ? (e.enter("thematicBreakSequence"), c(a)) : r >= 3 && (a === null || k(a)) ? (e.exit("thematicBreak"), t(a)) : n(a);
	}
	function c(t) {
		return t === i ? (e.consume(t), r++, c) : (e.exit("thematicBreakSequence"), j(t) ? M(e, s, "whitespace")(t) : s(t));
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/list.js
var N = {
	continuation: { tokenize: _n },
	exit: yn,
	name: "list",
	tokenize: gn
}, mn = {
	partial: !0,
	tokenize: bn
}, hn = {
	partial: !0,
	tokenize: vn
};
function gn(e, t, n) {
	let r = this, i = r.events[r.events.length - 1], a = i && i[1].type === "linePrefix" ? i[2].sliceSerialize(i[1], !0).length : 0, o = 0;
	return s;
	function s(t) {
		let i = r.containerState.type || (t === 42 || t === 43 || t === 45 ? "listUnordered" : "listOrdered");
		if (i === "listUnordered" ? !r.containerState.marker || t === r.containerState.marker : De(t)) {
			if (r.containerState.type || (r.containerState.type = i, e.enter(i, { _container: !0 })), i === "listUnordered") return e.enter("listItemPrefix"), t === 42 || t === 45 ? e.check(fn, n, l)(t) : l(t);
			if (!r.interrupt || t === 49) return e.enter("listItemPrefix"), e.enter("listItemValue"), c(t);
		}
		return n(t);
	}
	function c(t) {
		return De(t) && ++o < 10 ? (e.consume(t), c) : (!r.interrupt || o < 2) && (r.containerState.marker ? t === r.containerState.marker : t === 41 || t === 46) ? (e.exit("listItemValue"), l(t)) : n(t);
	}
	function l(t) {
		return e.enter("listItemMarker"), e.consume(t), e.exit("listItemMarker"), r.containerState.marker = r.containerState.marker || t, e.check(Xe, r.interrupt ? n : u, e.attempt(mn, f, d));
	}
	function u(e) {
		return r.containerState.initialBlankLine = !0, a++, f(e);
	}
	function d(t) {
		return j(t) ? (e.enter("listItemPrefixWhitespace"), e.consume(t), e.exit("listItemPrefixWhitespace"), f) : n(t);
	}
	function f(n) {
		return r.containerState.size = a + r.sliceSerialize(e.exit("listItemPrefix"), !0).length, t(n);
	}
}
function _n(e, t, n) {
	let r = this;
	return r.containerState._closeFlow = void 0, e.check(Xe, i, a);
	function i(n) {
		return r.containerState.furtherBlankLines = r.containerState.furtherBlankLines || r.containerState.initialBlankLine, M(e, t, "listItemIndent", r.containerState.size + 1)(n);
	}
	function a(n) {
		return r.containerState.furtherBlankLines || !j(n) ? (r.containerState.furtherBlankLines = void 0, r.containerState.initialBlankLine = void 0, o(n)) : (r.containerState.furtherBlankLines = void 0, r.containerState.initialBlankLine = void 0, e.attempt(hn, t, o)(n));
	}
	function o(i) {
		return r.containerState._closeFlow = !0, r.interrupt = void 0, M(e, e.attempt(N, t, n), "linePrefix", r.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 4)(i);
	}
}
function vn(e, t, n) {
	let r = this;
	return M(e, i, "listItemIndent", r.containerState.size + 1);
	function i(e) {
		let i = r.events[r.events.length - 1];
		return i && i[1].type === "listItemIndent" && i[2].sliceSerialize(i[1], !0).length === r.containerState.size ? t(e) : n(e);
	}
}
function yn(e) {
	e.exit(this.containerState.type);
}
function bn(e, t, n) {
	let r = this;
	return M(e, i, "listItemPrefixWhitespace", r.parser.constructs.disable.null.includes("codeIndented") ? void 0 : 5);
	function i(e) {
		let i = r.events[r.events.length - 1];
		return !j(e) && i && i[1].type === "listItemPrefixWhitespace" ? t(e) : n(e);
	}
}
// node_modules/.pnpm/micromark-core-commonmark@2.0.4/node_modules/micromark-core-commonmark/lib/setext-underline.js
var xn = {
	name: "setextUnderline",
	resolveTo: Sn,
	tokenize: Cn
};
function Sn(e, t) {
	let n = new Ie(), r = e.length, i, a, o;
	for (; r--;) if (e[r][0] === "enter") {
		if (e[r][1].type === "content") {
			i = r;
			break;
		}
		e[r][1].type === "paragraph" && (a = r);
	} else e[r][1].type === "content" && n.add(r, 1, []), !o && e[r][1].type === "definition" && (o = r);
	let s = {
		type: "setextHeading",
		start: { ...e[i][1].start },
		end: { ...e[e.length - 1][1].end }
	};
	return e[a][1].type = "setextHeadingText", o ? (n.add(a, 0, [[
		"enter",
		s,
		t
	]]), n.add(o + 1, 0, [[
		"exit",
		e[i][1],
		t
	]]), e[i][1].end = { ...e[o][1].end }) : e[i][1] = s, n.add(e.length, 0, [[
		"exit",
		s,
		t
	]]), n.consume(e), e;
}
function Cn(e, t, n) {
	let r = this, i;
	return a;
	function a(t) {
		let a = r.events.length, s;
		for (; a--;) if (r.events[a][1].type !== "lineEnding" && r.events[a][1].type !== "linePrefix" && r.events[a][1].type !== "content") {
			s = r.events[a][1].type === "paragraph";
			break;
		}
		return !r.parser.lazy[r.now().line] && (r.interrupt || s) ? (e.enter("setextHeadingLine"), i = t, o(t)) : n(t);
	}
	function o(t) {
		return e.enter("setextHeadingLineSequence"), s(t);
	}
	function s(t) {
		return t === i ? (e.consume(t), s) : (e.exit("setextHeadingLineSequence"), j(t) ? M(e, c, "lineSuffix")(t) : c(t));
	}
	function c(r) {
		return r === null || k(r) ? (e.exit("setextHeadingLine"), t(r)) : n(r);
	}
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/initialize/flow.js
var wn = { tokenize: Tn };
function Tn(e) {
	let t = this, n = e.attempt(Xe, r, e.attempt(this.parser.constructs.flowInitial, i, M(e, e.attempt(this.parser.constructs.flow, i, e.attempt(St, i)), "linePrefix")));
	return n;
	function r(r) {
		if (r === null) e.consume(r);
		else return e.enter("lineEndingBlank"), e.consume(r), e.exit("lineEndingBlank"), t.currentConstruct = void 0, n;
	}
	function i(r) {
		if (r === null) e.consume(r);
		else return e.enter("lineEnding"), e.consume(r), e.exit("lineEnding"), t.currentConstruct = void 0, n;
	}
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/initialize/text.js
var En = { resolveAll: An() }, Dn = kn("string"), On = kn("text");
function kn(e) {
	return {
		resolveAll: An(e === "text" ? jn : void 0),
		tokenize: t
	};
	function t(t) {
		let n = this, r = this.parser.constructs[e], i = t.attempt(r, a, o);
		return a;
		function a(e) {
			return c(e) ? i(e) : o(e);
		}
		function o(e) {
			if (e === null) t.consume(e);
			else return t.enter("data"), t.consume(e), s;
		}
		function s(e) {
			return c(e) ? (t.exit("data"), i(e)) : (t.consume(e), s);
		}
		function c(e) {
			if (e === null) return !0;
			let t = r[e], i = -1;
			if (t) for (; ++i < t.length;) {
				let e = t[i];
				if (!e.previous || e.previous.call(n, n.previous)) return !0;
			}
			return !1;
		}
	}
}
function An(e) {
	return t;
	function t(t, n) {
		let r = -1, i;
		for (; ++r <= t.length;) i === void 0 ? t[r] && t[r][1].type === "data" && (i = r, r++) : t[r] && t[r][1].type === "data" || (r !== i + 2 && (t[i][1].end = t[r - 1][1].end, t.splice(i + 2, r - i - 2), r = i + 2), i = void 0);
		return e ? e(t, n) : t;
	}
}
function jn(e, t) {
	let n = new Ie(), r = 0;
	for (; ++r <= e.length;) if ((r === e.length || e[r][1].type === "lineEnding") && e[r - 1][1].type === "data") {
		let i = e[r - 1][1], a = t.sliceStream(i), o = a.length, s = -1, c = 0, l;
		for (; o--;) {
			let e = a[o];
			if (typeof e == "string") {
				for (s = e.length; e.charCodeAt(s - 1) === 32;) c++, s--;
				if (s) break;
				s = -1;
			} else if (e === -2) l = !0, c++;
			else if (e !== -1) {
				o++;
				break;
			}
		}
		if (t._contentTypeTextTrailing && r === e.length && (c = 0), c) {
			let a = {
				type: r === e.length || l || c < 2 ? "lineSuffix" : "hardBreakTrailing",
				start: {
					_bufferIndex: o ? s : i.start._bufferIndex + s,
					_index: i.start._index + o,
					line: i.end.line,
					column: i.end.column - c,
					offset: i.end.offset - c
				},
				end: { ...i.end }
			};
			i.end = { ...a.start }, i.start.offset === i.end.offset ? Object.assign(i, a) : n.add(r, 0, [[
				"enter",
				a,
				t
			], [
				"exit",
				a,
				t
			]]);
		}
		r++;
	}
	return n.consume(e), e;
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/constructs.js
var Mn = /* @__PURE__ */ t({
	attentionMarkers: () => Bn,
	contentInitial: () => Pn,
	disable: () => Vn,
	document: () => Nn,
	flow: () => In,
	flowInitial: () => Fn,
	insideSpan: () => zn,
	string: () => Ln,
	text: () => Rn
}), Nn = {
	42: N,
	43: N,
	45: N,
	48: N,
	49: N,
	50: N,
	51: N,
	52: N,
	53: N,
	54: N,
	55: N,
	56: N,
	57: N,
	62: Qe
}, Pn = { 91: jt }, Fn = {
	[-2]: ut,
	[-1]: ut,
	32: ut
}, In = {
	35: Lt,
	42: fn,
	45: [xn, fn],
	60: Ht,
	61: xn,
	95: fn,
	96: ct,
	126: ct
}, Ln = {
	38: it,
	92: nt
}, Rn = {
	[-5]: un,
	[-4]: un,
	[-3]: un,
	33: on,
	38: it,
	42: We,
	60: [Je, qt],
	91: cn,
	92: [Ft, nt],
	93: Yt,
	95: We,
	96: mt
}, zn = { null: [We, En] }, Bn = { null: [42, 95] }, Vn = { null: [] };
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/create-tokenizer.js
function Hn(e, t, n) {
	let r = {
		_bufferIndex: -1,
		_index: 0,
		line: n && n.line || 1,
		column: n && n.column || 1,
		offset: n && n.offset || 0
	}, i = {}, a = [], o = [], s = [], c = {
		attempt: te(x),
		check: te(ee),
		consume: v,
		enter: y,
		exit: b,
		interrupt: te(ee, { interrupt: !0 })
	}, l = {
		code: null,
		containerState: {},
		defineSkip: h,
		events: [],
		now: m,
		parser: e,
		previous: null,
		sliceSerialize: f,
		sliceStream: p,
		write: d
	}, u = t.tokenize.call(l, c);
	return t.resolveAll && a.push(t), l;
	function d(e) {
		return o = ve(o, e), g(), o[o.length - 1] === null ? (ne(t, 0), l.events = Ue(a, l.events, l), l.events) : [];
	}
	function f(e, t) {
		return Wn(p(e), t);
	}
	function p(e) {
		return Un(o, e);
	}
	function m() {
		let { _bufferIndex: e, _index: t, line: n, column: i, offset: a } = r;
		return {
			_bufferIndex: e,
			_index: t,
			line: n,
			column: i,
			offset: a
		};
	}
	function h(e) {
		i[e.line] = e.column, S();
	}
	function g() {
		for (; r._index < o.length;) {
			let e = o[r._index];
			if (typeof e == "string") {
				let t = r._index;
				for (r._bufferIndex < 0 && (r._bufferIndex = 0); r._index === t && r._bufferIndex < e.length;) _(e.charCodeAt(r._bufferIndex));
			} else _(e);
		}
	}
	function _(e) {
		u = u(e);
	}
	function v(e) {
		k(e) ? (r.line++, r.column = 1, r.offset += e === -3 ? 2 : 1, S()) : e !== -1 && (r.column++, r.offset++), r._bufferIndex < 0 ? r._index++ : (r._bufferIndex++, r._bufferIndex === o[r._index].length && (r._bufferIndex = -1, r._index++)), l.previous = e;
	}
	function y(e, t) {
		let n = t || {};
		return n.type = e, n.start = m(), l.events.push([
			"enter",
			n,
			l
		]), s.push(n), n;
	}
	function b(e) {
		let t = s.pop();
		return t.end = m(), l.events.push([
			"exit",
			t,
			l
		]), t;
	}
	function x(e, t) {
		ne(e, t.from);
	}
	function ee(e, t) {
		t.restore();
	}
	function te(e, t) {
		return n;
		function n(n, r, i) {
			let a, o, s, u;
			return Array.isArray(n) ? f(n) : "tokenize" in n ? f([n]) : d(n);
			function d(e) {
				return t;
				function t(t) {
					let n = t !== null && e[t], r = t !== null && e.null;
					return f([...Array.isArray(n) ? n : n ? [n] : [], ...Array.isArray(r) ? r : r ? [r] : []])(t);
				}
			}
			function f(e) {
				return a = e, o = 0, e.length === 0 ? i : p(e[o]);
			}
			function p(e) {
				return n;
				function n(n) {
					return u = re(), s = e, e.partial || (l.currentConstruct = e), e.name && l.parser.constructs.disable.null.includes(e.name) ? h(n) : e.tokenize.call(t ? Object.assign(Object.create(l), t) : l, c, m, h)(n);
				}
			}
			function m(t) {
				return e(s, u), r;
			}
			function h(e) {
				return u.restore(), ++o < a.length ? p(a[o]) : i;
			}
		}
	}
	function ne(e, t) {
		e.resolveAll && !a.includes(e) && a.push(e), e.resolve && _e(l.events, t, l.events.length - t, e.resolve(l.events.slice(t), l)), e.resolveTo && (l.events = e.resolveTo(l.events, l));
	}
	function re() {
		let e = m(), t = l.previous, n = l.currentConstruct, i = l.events.length, a = Array.from(s);
		return {
			from: i,
			restore: o
		};
		function o() {
			r = e, l.previous = t, l.currentConstruct = n, l.events.length = i, s = a, S();
		}
	}
	function S() {
		r.line in i && r.column < 2 && (r.column = i[r.line], r.offset += i[r.line] - 1);
	}
}
function Un(e, t) {
	let n = t.start._index, r = t.start._bufferIndex, i = t.end._index, a = t.end._bufferIndex, o;
	if (n === i) o = [e[n].slice(r, a)];
	else {
		if (o = e.slice(n, i), r > -1) {
			let e = o[0];
			typeof e == "string" ? o[0] = e.slice(r) : o.shift();
		}
		a > 0 && o.push(e[i].slice(0, a));
	}
	return o;
}
function Wn(e, t) {
	let n = -1, r = [], i;
	for (; ++n < e.length;) {
		let a = e[n], o;
		if (typeof a == "string") o = a;
		else switch (a) {
			case -5:
				o = "\r";
				break;
			case -4:
				o = "\n";
				break;
			case -3:
				o = "\r\n";
				break;
			case -2:
				o = t ? " " : "	";
				break;
			case -1:
				if (!t && i) continue;
				o = " ";
				break;
			default: o = String.fromCharCode(a);
		}
		i = a === -2, r.push(o);
	}
	return r.join("");
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/parse.js
function Gn(e) {
	let t = {
		constructs: be([Mn, ...(e || {}).extensions || []]),
		content: n(Pe),
		defined: [],
		document: n(Re),
		flow: n(wn),
		lazy: {},
		string: n(Dn),
		text: n(On)
	};
	return t;
	function n(e) {
		return n;
		function n(n) {
			return Hn(t, e, n);
		}
	}
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/postprocess.js
function Kn(e) {
	for (; !bt(e););
	return e;
}
// node_modules/.pnpm/micromark@4.0.3/node_modules/micromark/lib/preprocess.js
var qn = /[\0\t\n\r]/g;
function Jn() {
	let e = 1, t = "", n = !0, r;
	return i;
	function i(i, a, o) {
		i = t + (typeof i == "string" ? i.toString() : new TextDecoder(a || void 0).decode(i));
		let s = [], c = 0;
		for (t = "", n &&= (i.charCodeAt(0) === 65279 && c++, void 0); c < i.length;) {
			qn.lastIndex = c;
			let n = qn.exec(i), a = n && n.index !== void 0 ? n.index : i.length, o = i.charCodeAt(a);
			if (!n) {
				t = i.slice(c);
				break;
			}
			if (o === 10 && c === a && r) s.push(-3), r = void 0;
			else switch (r &&= (s.push(-5), void 0), c < a && (s.push(i.slice(c, a)), e += a - c), o) {
				case 0:
					s.push(65533), e++;
					break;
				case 9: {
					let t = Math.ceil(e / 4) * 4;
					for (s.push(-2); e++ < t;) s.push(-1);
					break;
				}
				case 10:
					s.push(-4), e = 1;
					break;
				default: r = !0, e = 1;
			}
			c = a + 1;
		}
		return o && (r && s.push(-5), t && s.push(t), s.push(null)), s;
	}
}
// node_modules/.pnpm/micromark-util-decode-string@2.0.1/node_modules/micromark-util-decode-string/index.js
var Yn = /\\([!-/:-@[-`{-~])|&(#(?:\d{1,7}|x[\da-f]{1,6})|[\da-z]{1,31});/gi;
function Xn(e) {
	return e.replace(Yn, Zn);
}
function Zn(e, t, n) {
	if (t) return t;
	if (n.charCodeAt(0) === 35) {
		let e = n.charCodeAt(1), t = e === 120 || e === 88;
		return Ce(n.slice(t ? 2 : 1), t ? 16 : 10);
	}
	return ge(n) || e;
}
// node_modules/.pnpm/unist-util-stringify-position@4.0.0/node_modules/unist-util-stringify-position/lib/index.js
function Qn(e) {
	return !e || typeof e != "object" ? "" : "position" in e || "type" in e ? er(e.position) : "start" in e || "end" in e ? er(e) : "line" in e || "column" in e ? $n(e) : "";
}
function $n(e) {
	return tr(e && e.line) + ":" + tr(e && e.column);
}
function er(e) {
	return $n(e && e.start) + "-" + $n(e && e.end);
}
function tr(e) {
	return e && typeof e == "number" ? e : 1;
}
// node_modules/.pnpm/mdast-util-from-markdown@2.1.0/node_modules/mdast-util-from-markdown/lib/index.js
var nr = {}.hasOwnProperty;
function rr(e, t, n) {
	return t && typeof t == "object" && (n = t, t = void 0), ir(n)(Kn(Gn(n).document().write(Jn()(e, t, !0))));
}
function ir(e) {
	let t = {
		afterExit: [],
		beforeEnter: [],
		transforms: [],
		canContainEols: [
			"emphasis",
			"fragment",
			"heading",
			"paragraph",
			"strong"
		],
		enter: {
			autolink: o(A),
			autolinkProtocol: S,
			autolinkEmail: S,
			atxHeading: o(De),
			blockQuote: o(Se),
			characterEscape: S,
			characterReference: S,
			codeFenced: o(D),
			codeFencedFenceInfo: s,
			codeFencedFenceMeta: s,
			codeIndented: o(D, s),
			codeText: o(O, s),
			codeTextData: S,
			data: S,
			codeFlowValue: S,
			definition: o(Te),
			definitionDestinationString: s,
			definitionLabelString: s,
			definitionTitleString: s,
			emphasis: o(Ee),
			hardBreakEscape: o(Oe),
			hardBreakTrailing: o(Oe),
			htmlFlow: o(ke, s),
			htmlFlowData: S,
			htmlText: o(ke, s),
			htmlTextData: S,
			image: o(k),
			label: s,
			link: o(A),
			listItem: o(Ae),
			listItemValue: p,
			listOrdered: o(j, f),
			listUnordered: o(j),
			paragraph: o(je),
			reference: me,
			referenceString: s,
			resourceDestinationString: s,
			resourceTitleString: s,
			setextHeading: o(De),
			strong: o(Me),
			thematicBreak: o(Ne)
		},
		exit: {
			atxHeading: l(),
			atxHeadingSequence: ee,
			autolink: l(),
			autolinkEmail: xe,
			autolinkProtocol: be,
			blockQuote: l(),
			characterEscapeValue: C,
			characterReferenceMarkerHexadecimal: _e,
			characterReferenceMarkerNumeric: _e,
			characterReferenceValue: ve,
			characterReference: ye,
			codeFenced: l(_),
			codeFencedFence: g,
			codeFencedFenceInfo: m,
			codeFencedFenceMeta: h,
			codeFlowValue: C,
			codeIndented: l(v),
			codeText: l(oe),
			codeTextData: C,
			data: C,
			definition: l(),
			definitionDestinationString: x,
			definitionLabelString: y,
			definitionTitleString: b,
			emphasis: l(),
			hardBreakEscape: l(ie),
			hardBreakTrailing: l(ie),
			htmlFlow: l(T),
			htmlFlowData: C,
			htmlText: l(ae),
			htmlTextData: C,
			image: l(ce),
			label: E,
			labelText: le,
			lineEnding: w,
			link: l(se),
			listItem: l(),
			listOrdered: l(),
			listUnordered: l(),
			paragraph: l(),
			referenceString: he,
			resourceDestinationString: ue,
			resourceTitleString: fe,
			resource: pe,
			setextHeading: l(re),
			setextHeadingLineSequence: ne,
			setextHeadingText: te,
			strong: l(),
			thematicBreak: l()
		}
	};
	or(t, (e || {}).mdastExtensions || []);
	let n = {};
	return r;
	function r(e) {
		let r = {
			type: "root",
			children: [],
			position: void 0
		}, o = {
			stack: [r],
			tokenStack: [],
			config: t,
			enter: c,
			exit: u,
			buffer: s,
			resume: d,
			data: n
		}, l = [], f = [], p = -1;
		for (; ++p < e.length;) f.push(e[p]), (e[p][1].type === "listOrdered" || e[p][1].type === "listUnordered") && (e[p][0] === "enter" ? l.push(f.length - 1) : a(f, l.pop()));
		for (e = f, p = -1; ++p < e.length;) {
			let n = t[e[p][0]];
			e[p][0] === "enter" && t.beforeEnter.length > 0 && i(t.beforeEnter, {
				...o,
				sliceSerialize: e[p][2].sliceSerialize
			}, e[p][1]), nr.call(n, e[p][1].type) && n[e[p][1].type].call({
				...o,
				sliceSerialize: e[p][2].sliceSerialize
			}, e[p][1]), e[p][0] === "exit" && t.afterExit.length > 0 && i(t.afterExit, {
				...o,
				sliceSerialize: e[p][2].sliceSerialize
			}, e[p][1]);
		}
		if (o.tokenStack.length > 0) {
			let e = o.tokenStack[o.tokenStack.length - 1];
			(e[1] || cr).call(o, void 0, e[0]);
		}
		for (r.position = {
			start: ar(e.length > 0 ? e[0][1].start : {
				line: 1,
				column: 1,
				offset: 0
			}),
			end: ar(e.length > 0 ? e[e.length - 2][1].end : {
				line: 1,
				column: 1,
				offset: 0
			})
		}, p = -1; ++p < t.transforms.length;) r = t.transforms[p](r) || r;
		return r;
	}
	function i(e, t, n) {
		let r = -1;
		for (; ++r < e.length;) e[r].call(t, n);
	}
	function a(e, t) {
		let n = e.length - 1, r = t - 1, i = -1, a = !1, o, s, c, l, u = [];
		for (; ++r <= n;) {
			let t = e[r];
			switch (t[1].type) {
				case "listUnordered":
				case "listOrdered":
				case "blockQuote":
					t[0] === "enter" ? i++ : i--, l = void 0;
					break;
				case "lineEndingBlank":
					t[0] === "enter" && (o && !l && !i && !c && (c = r), l = void 0);
					break;
				case "linePrefix":
				case "listItemValue":
				case "listItemMarker":
				case "listItemPrefix":
				case "listItemPrefixWhitespace": break;
				default: l = void 0;
			}
			if (!i && t[0] === "enter" && t[1].type === "listItemPrefix" || i === -1 && t[0] === "exit" && (t[1].type === "listUnordered" || t[1].type === "listOrdered")) {
				if (o) {
					let n = r;
					for (s = void 0; n--;) {
						let t = e[n];
						if (t[1].type === "lineEnding" || t[1].type === "lineEndingBlank") {
							if (t[0] === "exit") continue;
							s && (e[s][1].type = "lineEndingBlank", a = !0), t[1].type = "lineEnding", s = n;
						} else if (t[1].type !== "linePrefix" && t[1].type !== "blockQuotePrefix" && t[1].type !== "blockQuotePrefixWhitespace" && t[1].type !== "blockQuoteMarker" && t[1].type !== "listItemIndent") break;
					}
					c && (!s || c < s) && (o._spread = !0), o.end = Object.assign({}, s ? e[s][1].start : t[1].end), u.push({
						at: s || r,
						event: [
							"exit",
							o,
							t[2]
						]
					});
				}
				if (t[1].type === "listItemPrefix") {
					let e = {
						type: "listItem",
						_spread: !1,
						start: Object.assign({}, t[1].start),
						end: void 0
					};
					o = e, u.push({
						at: r,
						event: [
							"enter",
							e,
							t[2]
						]
					}), c = void 0, l = !0;
				}
			}
		}
		let d = e.splice(t), f = 0;
		for (r = -1; ++r < d.length;) {
			for (; f < u.length && u[f].at === t + r;) e.push(u[f++].event);
			e.push(d[r]);
		}
		e[t][1]._spread = a;
	}
	function o(e, t) {
		return n;
		function n(n) {
			c.call(this, e(n), n), t && t.call(this, n);
		}
	}
	function s() {
		this.stack.push({
			type: "fragment",
			children: []
		});
	}
	function c(e, t, n) {
		this.stack[this.stack.length - 1].children.push(e), this.stack.push(e), this.tokenStack.push([t, n || void 0]), e.position = {
			start: ar(t.start),
			end: void 0
		};
	}
	function l(e) {
		return t;
		function t(t) {
			e && e.call(this, t), u.call(this, t);
		}
	}
	function u(e, t) {
		let n = this.stack.pop(), r = this.tokenStack.pop();
		if (!r) throw Error("Cannot close `" + e.type + "` (" + Qn({
			start: e.start,
			end: e.end
		}) + "): it’s not open");
		r[0].type !== e.type && (t ? t.call(this, e, r[0]) : (r[1] || cr).call(this, e, r[0])), n.position.end = ar(e.end);
	}
	function d() {
		return de(this.stack.pop());
	}
	function f() {
		this.data.expectingFirstListItemValue = !0;
	}
	function p(e) {
		if (this.data.expectingFirstListItemValue) {
			let t = this.stack[this.stack.length - 2];
			t.start = Number.parseInt(this.sliceSerialize(e), 10), this.data.expectingFirstListItemValue = void 0;
		}
	}
	function m() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.lang = e;
	}
	function h() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.meta = e;
	}
	function g() {
		this.data.flowCodeInside || (this.buffer(), this.data.flowCodeInside = !0);
	}
	function _() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.value = e.replace(/^(\r?\n|\r)|(\r?\n|\r)$/g, ""), this.data.flowCodeInside = void 0;
	}
	function v() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.value = e.replace(/(\r?\n|\r)$/g, "");
	}
	function y(e) {
		let t = this.resume(), n = this.stack[this.stack.length - 1];
		n.label = t, n.identifier = we(this.sliceSerialize(e)).toLowerCase();
	}
	function b() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.title = e;
	}
	function x() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.url = e;
	}
	function ee(e) {
		let t = this.stack[this.stack.length - 1];
		t.depth ||= this.sliceSerialize(e).length;
	}
	function te() {
		this.data.setextHeadingSlurpLineEnding = !0;
	}
	function ne(e) {
		let t = this.stack[this.stack.length - 1];
		t.depth = this.sliceSerialize(e).codePointAt(0) === 61 ? 1 : 2;
	}
	function re() {
		this.data.setextHeadingSlurpLineEnding = void 0;
	}
	function S(e) {
		let t = this.stack[this.stack.length - 1].children, n = t[t.length - 1];
		n && n.type === "text" || (n = M(), n.position = {
			start: ar(e.start),
			end: void 0
		}, t.push(n)), this.stack.push(n);
	}
	function C(e) {
		let t = this.stack.pop();
		t.value += this.sliceSerialize(e), t.position.end = ar(e.end);
	}
	function w(e) {
		let n = this.stack[this.stack.length - 1];
		if (this.data.atHardBreak) {
			let t = n.children[n.children.length - 1];
			t.position.end = ar(e.end), this.data.atHardBreak = void 0;
		} else !this.data.setextHeadingSlurpLineEnding && t.canContainEols.includes(n.type) && (S.call(this, e), C.call(this, e));
	}
	function ie() {
		this.data.atHardBreak = !0;
	}
	function T() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.value = e;
	}
	function ae() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.value = e;
	}
	function oe() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.value = e;
	}
	function se() {
		let e = this.stack[this.stack.length - 1];
		if (this.data.inReference) {
			let t = this.data.referenceType || "shortcut";
			e.type += "Reference", e.referenceType = t, delete e.url, delete e.title;
		} else delete e.identifier, delete e.label;
		this.data.referenceType = void 0;
	}
	function ce() {
		let e = this.stack[this.stack.length - 1];
		if (this.data.inReference) {
			let t = this.data.referenceType || "shortcut";
			e.type += "Reference", e.referenceType = t, delete e.url, delete e.title;
		} else delete e.identifier, delete e.label;
		this.data.referenceType = void 0;
	}
	function le(e) {
		let t = this.sliceSerialize(e), n = this.stack[this.stack.length - 2];
		n.label = Xn(t), n.identifier = we(t).toLowerCase();
	}
	function E() {
		let e = this.stack[this.stack.length - 1], t = this.resume(), n = this.stack[this.stack.length - 1];
		this.data.inReference = !0, n.type === "link" ? n.children = e.children : n.alt = t;
	}
	function ue() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.url = e;
	}
	function fe() {
		let e = this.resume(), t = this.stack[this.stack.length - 1];
		t.title = e;
	}
	function pe() {
		this.data.inReference = void 0;
	}
	function me() {
		this.data.referenceType = "collapsed";
	}
	function he(e) {
		let t = this.resume(), n = this.stack[this.stack.length - 1];
		n.label = t, n.identifier = we(this.sliceSerialize(e)).toLowerCase(), this.data.referenceType = "full";
	}
	function _e(e) {
		this.data.characterReferenceType = e.type;
	}
	function ve(e) {
		let t = this.sliceSerialize(e), n = this.data.characterReferenceType, r;
		n ? (r = Ce(t, n === "characterReferenceMarkerNumeric" ? 10 : 16), this.data.characterReferenceType = void 0) : r = ge(t);
		let i = this.stack[this.stack.length - 1];
		i.value += r;
	}
	function ye(e) {
		let t = this.stack.pop();
		t.position.end = ar(e.end);
	}
	function be(e) {
		C.call(this, e);
		let t = this.stack[this.stack.length - 1];
		t.url = this.sliceSerialize(e);
	}
	function xe(e) {
		C.call(this, e);
		let t = this.stack[this.stack.length - 1];
		t.url = "mailto:" + this.sliceSerialize(e);
	}
	function Se() {
		return {
			type: "blockquote",
			children: [],
			position: void 0
		};
	}
	function D() {
		return {
			type: "code",
			lang: null,
			meta: null,
			value: "",
			position: void 0
		};
	}
	function O() {
		return {
			type: "inlineCode",
			value: "",
			position: void 0
		};
	}
	function Te() {
		return {
			type: "definition",
			identifier: "",
			label: null,
			title: null,
			url: "",
			position: void 0
		};
	}
	function Ee() {
		return {
			type: "emphasis",
			children: [],
			position: void 0
		};
	}
	function De() {
		return {
			type: "heading",
			depth: 0,
			children: [],
			position: void 0
		};
	}
	function Oe() {
		return {
			type: "break",
			position: void 0
		};
	}
	function ke() {
		return {
			type: "html",
			value: "",
			position: void 0
		};
	}
	function k() {
		return {
			type: "image",
			title: null,
			url: "",
			alt: null,
			position: void 0
		};
	}
	function A() {
		return {
			type: "link",
			title: null,
			url: "",
			children: [],
			position: void 0
		};
	}
	function j(e) {
		return {
			type: "list",
			ordered: e.type === "listOrdered",
			start: null,
			spread: e._spread,
			children: [],
			position: void 0
		};
	}
	function Ae(e) {
		return {
			type: "listItem",
			spread: e._spread,
			checked: null,
			children: [],
			position: void 0
		};
	}
	function je() {
		return {
			type: "paragraph",
			children: [],
			position: void 0
		};
	}
	function Me() {
		return {
			type: "strong",
			children: [],
			position: void 0
		};
	}
	function M() {
		return {
			type: "text",
			value: "",
			position: void 0
		};
	}
	function Ne() {
		return {
			type: "thematicBreak",
			position: void 0
		};
	}
}
function ar(e) {
	return {
		line: e.line,
		column: e.column,
		offset: e.offset
	};
}
function or(e, t) {
	let n = -1;
	for (; ++n < t.length;) {
		let r = t[n];
		Array.isArray(r) ? or(e, r) : sr(e, r);
	}
}
function sr(e, t) {
	let n;
	for (n in t) if (nr.call(t, n)) switch (n) {
		case "canContainEols": {
			let r = t[n];
			r && e[n].push(...r);
			break;
		}
		case "transforms": {
			let r = t[n];
			r && e[n].push(...r);
			break;
		}
		case "afterExit":
		case "beforeEnter": {
			let r = t[n];
			r && e[n].push(r);
			break;
		}
		case "enter":
		case "exit": {
			let r = t[n];
			r && Object.assign(e[n], r);
			break;
		}
	}
}
function cr(e, t) {
	throw Error(e ? "Cannot close `" + e.type + "` (" + Qn({
		start: e.start,
		end: e.end
	}) + "): a different token (`" + t.type + "`, " + Qn({
		start: t.start,
		end: t.end
	}) + ") is open" : "Cannot close document, a token (`" + t.type + "`, " + Qn({
		start: t.start,
		end: t.end
	}) + ") is still open");
}
// node_modules/.pnpm/remark-parse@11.0.0/node_modules/remark-parse/lib/index.js
function lr(e) {
	let t = this;
	t.parser = n;
	function n(n) {
		return rr(n, {
			...t.data("settings"),
			...e,
			extensions: t.data("micromarkExtensions") || [],
			mdastExtensions: t.data("fromMarkdownExtensions") || []
		});
	}
}
// node_modules/.pnpm/unist-util-is@6.0.1/node_modules/unist-util-is/lib/index.js
var ur = (function(e) {
	if (e == null) return hr;
	if (typeof e == "function") return mr(e);
	if (typeof e == "object") return Array.isArray(e) ? dr(e) : fr(e);
	if (typeof e == "string") return pr(e);
	throw Error("Expected function, string, or object as test");
});
function dr(e) {
	let t = [], n = -1;
	for (; ++n < e.length;) t[n] = ur(e[n]);
	return mr(r);
	function r(...e) {
		let n = -1;
		for (; ++n < t.length;) if (t[n].apply(this, e)) return !0;
		return !1;
	}
}
function fr(e) {
	let t = e;
	return mr(n);
	function n(n) {
		let r = n, i;
		for (i in e) if (r[i] !== t[i]) return !1;
		return !0;
	}
}
function pr(e) {
	return mr(t);
	function t(t) {
		return t && t.type === e;
	}
}
function mr(e) {
	return t;
	function t(t, n, r) {
		return !!(gr(t) && e.call(this, t, typeof n == "number" ? n : void 0, r || void 0));
	}
}
function hr() {
	return !0;
}
function gr(e) {
	return typeof e == "object" && !!e && "type" in e;
}
// node_modules/.pnpm/mdast-util-phrasing@4.1.0/node_modules/mdast-util-phrasing/lib/index.js
var _r = ur([
	"break",
	"delete",
	"emphasis",
	"footnote",
	"footnoteReference",
	"image",
	"imageReference",
	"inlineCode",
	"inlineMath",
	"link",
	"linkReference",
	"mdxJsxTextElement",
	"mdxTextExpression",
	"strong",
	"text",
	"textDirective"
]), vr = {}.hasOwnProperty;
function yr(e, t) {
	let n = t || {};
	function r(t, ...n) {
		let i = r.invalid, a = r.handlers;
		if (t && vr.call(t, e)) {
			let n = String(t[e]);
			i = vr.call(a, n) ? a[n] : r.unknown;
		}
		if (i) return i.call(this, t, ...n);
	}
	return r.handlers = n.handlers || {}, r.invalid = n.invalid, r.unknown = n.unknown, r;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/configure.js
var br = {}.hasOwnProperty;
function xr(e, t) {
	let n = -1, r;
	if (t.extensions) for (; ++n < t.extensions.length;) xr(e, t.extensions[n]);
	for (r in t) if (br.call(t, r)) switch (r) {
		case "extensions": break;
		/* c8 ignore next 4 */
		case "unsafe":
			Sr(e[r], t[r]);
			break;
		case "join":
			Sr(e[r], t[r]);
			break;
		case "handlers":
			Cr(e[r], t[r]);
			break;
		default: e.options[r] = t[r];
	}
	return e;
}
function Sr(e, t) {
	t && e.push(...t);
}
function Cr(e, t) {
	t && Object.assign(e, t);
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/blockquote.js
function wr(e, t, n, r) {
	let i = n.enter("blockquote"), a = n.createTracker(r);
	a.move("> "), a.shift(2);
	let o = n.indentLines(n.containerFlow(e, a.current()), Tr);
	return i(), o;
}
function Tr(e, t, n) {
	return ">" + (n ? "" : " ") + e;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/pattern-in-scope.js
function Er(e, t) {
	return Dr(e, t.inConstruct, !0) && !Dr(e, t.notInConstruct, !1);
}
function Dr(e, t, n) {
	if (typeof t == "string" && (t = [t]), !t || t.length === 0) return n;
	let r = -1;
	for (; ++r < t.length;) if (e.includes(t[r])) return !0;
	return !1;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/break.js
function Or(e, t, n, r) {
	let i = -1;
	for (; ++i < n.unsafe.length;) {
		let e = n.unsafe[i];
		if (e.character === "\n" && !e.before && !e.after && Er(n.stack, e)) return /[\t ]/.test(r.before) ? "" : " ";
	}
	return "\\\n";
}
// node_modules/.pnpm/longest-streak@3.1.0/node_modules/longest-streak/index.js
function kr(e, t) {
	let n = String(e), r = n.indexOf(t), i = r, a = 0, o = 0;
	if (typeof t != "string") throw TypeError("Expected substring");
	for (; r !== -1;) r === i ? ++a > o && (o = a) : a = 1, i = r + t.length, r = n.indexOf(t, i);
	return o;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/format-code-as-indented.js
function Ar(e, t) {
	return !(t.options.fences !== !1 || !e.value || e.lang || !/[^\n\r ]/.test(e.value) || /^[\t ]*(?:[\n\r]|$)|(?:^|[\n\r])[\t ]*$/.test(e.value));
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-fence.js
function jr(e) {
	let t = e.options.fence || "`";
	if (t !== "`" && t !== "~") throw Error("Cannot serialize code with `" + t + "` for `options.fence`, expected `` ` `` or `~`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/code.js
function Mr(e, t, n, r) {
	let i = jr(n), a = e.value || "";
	if (Ar(e, n)) {
		let e = n.enter("codeIndented"), t = n.indentLines(a, Nr);
		return e(), t;
	}
	let o = n.createTracker(r), s = i.repeat(Math.max(kr(a, i) + 1, 3)), c = n.enter("codeFenced"), l = i === "`" ? "GraveAccent" : "Tilde", u = o.move(s);
	if (e.lang) {
		let t = n.enter(`codeFencedLang${l}`);
		u += o.move(n.safe(e.lang, {
			before: u,
			after: " ",
			encode: ["`"],
			...o.current()
		})), t();
	}
	if (e.lang && e.meta) {
		let t = n.enter(`codeFencedMeta${l}`);
		u += o.move(" "), u += o.move(n.safe(e.meta, {
			before: u,
			after: "\n",
			encode: ["`"],
			...o.current()
		})), t();
	}
	return u += o.move("\n"), a && (u += o.move(a + "\n")), u += o.move(s), c(), u;
}
function Nr(e, t, n) {
	return (n ? "" : "    ") + e;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-quote.js
function Pr(e) {
	let t = e.options.quote || "\"";
	if (t !== "\"" && t !== "'") throw Error("Cannot serialize title with `" + t + "` for `options.quote`, expected `\"`, or `'`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/definition.js
function Fr(e, t, n, r) {
	let i = Pr(n), a = i === "\"" ? "Quote" : "Apostrophe", o = n.enter("definition"), s = n.enter("label"), c = n.createTracker(r), l = c.move("[");
	return l += c.move(n.safe(n.associationId(e), {
		before: l,
		after: "]",
		...c.current()
	})), l += c.move("]: "), s(), !e.url || /[\0- \u007F]/.test(e.url) ? (s = n.enter("destinationLiteral"), l += c.move("<"), l += c.move(n.safe(e.url, {
		before: l,
		after: ">",
		...c.current()
	})), l += c.move(">")) : (s = n.enter("destinationRaw"), l += c.move(n.safe(e.url, {
		before: l,
		after: e.title ? " " : "\n",
		...c.current()
	}))), s(), e.title && (s = n.enter(`title${a}`), l += c.move(" " + i), l += c.move(n.safe(e.title, {
		before: l,
		after: i,
		...c.current()
	})), l += c.move(i), s()), o(), l;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-emphasis.js
function Ir(e) {
	let t = e.options.emphasis || "*";
	if (t !== "*" && t !== "_") throw Error("Cannot serialize emphasis with `" + t + "` for `options.emphasis`, expected `*`, or `_`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/character.js
function Lr(e) {
	let t = e.codePointAt(0);
	return t === void 0 ? "" : String.fromCodePoint(t);
}
function Rr(e) {
	let t = e.codePointAt(e.length - 2);
	return t !== void 0 && t > 65535 ? e.slice(-2) : e.slice(-1);
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/encode-character-reference.js
function zr(e) {
	return "&#x" + e.toString(16).toUpperCase() + ";";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/encode-info.js
function Br(e, t, n) {
	let r = He(e), i = He(t);
	return r === void 0 ? i === void 0 ? n === "_" ? {
		inside: !0,
		outside: !0
	} : {
		inside: !1,
		outside: !1
	} : i === 1 ? {
		inside: !0,
		outside: !0
	} : {
		inside: !1,
		outside: !0
	} : r === 1 ? i === void 0 ? {
		inside: !1,
		outside: !1
	} : i === 1 ? {
		inside: !0,
		outside: !0
	} : {
		inside: !1,
		outside: !1
	} : i === void 0 ? {
		inside: !1,
		outside: !1
	} : i === 1 ? {
		inside: !0,
		outside: !1
	} : {
		inside: !1,
		outside: !1
	};
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/html-kind.js
function Vr(e) {
	if (e.charCodeAt(0) !== 60) return;
	let t = e.charCodeAt(1);
	if (t === 33) {
		let t = e.charCodeAt(2);
		return t === 45 ? 2 : t === 91 ? 5 : D(t) ? 4 : void 0;
	}
	if (t === 63) return 3;
	let n = t === 47, r = n ? 2 : 1, i = r, a = e.charCodeAt(i);
	if (!D(a)) return;
	for (; a === 45 || O(a);) a = e.charCodeAt(++i);
	let o = e.slice(r, i).toLowerCase();
	return !n && a !== 47 && Vt.includes(o) ? 1 : Bt.includes(o) ? 6 : 7;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/container-phrasing.js
var Hr = /* @__PURE__ */ new Map([["*", !1], ["_", !0]]);
function Ur(e, t, n) {
	let r = /* @__PURE__ */ new Set();
	for (let i of e) {
		let e = n.get(i);
		if (e && i.attention && t.some((e) => e.attention === i.attention)) for (let t of e.tokens) r.add(t.attention);
	}
	let i = [];
	for (let t of e) t.attention && r.delete(t.attention) && i.push(t.attention);
	return i.reverse(), i;
}
function Wr(e, t, n) {
	let r = [], i = /* @__PURE__ */ new Map(), a = /* @__PURE__ */ new Map(), o = 0;
	for (; o < e.length;) {
		if (!e[o].attention) {
			o++;
			continue;
		}
		let s = e[o].value.charAt(0), c = Hr.get(s), l = o + 1;
		for (; l < e.length && e[l].attention && e[l].value.charAt(0) === s;) l++;
		let u = o ? e[o - 1].value : t, d = He(u.charCodeAt(u.length - 1)), f = He((l < e.length ? e[l].value : n).charCodeAt(0)), p = !f || f === 2 && !!d, m = !d || d === 2 && !!f, h = {
			tokens: e.slice(o, l),
			markers: [],
			start: 0,
			end: 0,
			open: c ? p && (!!d || !m) : p,
			close: c ? m && (!!f || !p) : m,
			split: c !== void 0
		};
		for (let e of h.tokens) {
			i.set(e, [...h.tokens]), a.set(e, h);
			let t = e.value.length;
			for (; t--;) h.markers.push(e);
		}
		h.end = h.markers.length, r.push(h), o = l;
	}
	for (Jr(r, i), o = -1; ++o < e.length;) {
		let t = i.get(e[o]);
		if (t) return {
			attention: Ur(e, t, a),
			index: o
		};
	}
}
function Gr(e, t, n) {
	return $r(Xr(e, t, n), n.before, n.after);
}
function Kr(e, t) {
	let n = e[t ? 0 : e.length - 1];
	if (!n || n.attention) return;
	let r = t ? Lr(n.value) : Rr(n.value), i = zr(r.codePointAt(0));
	if (t) n.value = i + n.value.slice(r.length);
	else {
		let e = n.value.slice(0, n.value.length - r.length), t = 0;
		for (; e.charCodeAt(e.length - 1 - t) === 92;) t++;
		t % 2 && (e += "\\"), n.value = e + i;
	}
}
function qr(e, t, n, r, i) {
	for (let a of n.attention) {
		let o = t.get(a) || a.sequences[0];
		for (let s of a.sequences) {
			if (s === o) continue;
			let c = new Map(t);
			c.set(a, s);
			let l = Qr(e, c, r, i), u = Wr(l, r, i);
			if (!u || u.index > n.index) return {
				chosen: c,
				mistake: u,
				tokens: l
			};
		}
	}
}
function Jr(e, t) {
	let n = /* @__PURE__ */ new Map();
	for (let r of e) {
		let e = r.tokens[0].value.charAt(0), i = n.get(e);
		i || (i = [], n.set(e, i));
		let a = r.close ? i.length : 0;
		for (; a-- && r.start < r.end;) {
			let e = i[a], n = e.end - e.start, o = r.end - r.start;
			if (r.split ? (e.close || r.open) && o % 3 && !((n + o) % 3) : n !== e.markers.length || n !== o) continue;
			let s = r.split ? n > 1 && o > 1 ? 2 : 1 : o, c = e.markers.slice(e.end - s, e.end), l = r.markers.slice(r.start, r.start + s), u = c[0], d = l[0];
			if (u === c[s - 1] && u.value.length === s && d === l[s - 1] && d.value.length === s && u.attention === d.attention) t.delete(u), t.delete(d);
			else {
				let e = [.../* @__PURE__ */ new Set([...c, ...l])];
				for (let n of e) {
					let r = t.get(n);
					r && r.push(...e);
				}
			}
			e.end -= s, r.start += s, e.start === e.end && i.splice(a, 1), a = i.length;
		}
		r.open && r.start < r.end && i.push(r);
	}
}
function Yr(e, t, n) {
	if (t.length === 0) throw Error("Cannot serialize `" + e + "` as attention without markers, expected one or more markers");
	if (n.length === 0) throw Error("Cannot serialize `" + e + "` as attention without sizes, expected one or more sizes");
	for (let t of n) if (!Number.isSafeInteger(t) || t < 1) throw Error("Cannot serialize `" + e + "` as attention with `" + t + "` as size, expected positive integer");
	let r = [];
	for (let i of t) {
		if (i.length !== 1) throw Error("Cannot serialize `" + e + "` as attention with `" + i + "` as marker, expected a single ascii character");
		if (!ke(i.charCodeAt(0))) throw Error("Cannot serialize `" + e + "` as attention with `" + i + "` as marker, expected ascii punctuation");
		for (let e of n) r.push(i.repeat(e));
	}
	return r;
}
function Xr(e, t, n) {
	let r = t.indexStack, i = e.children || [], a = [], o = -1, s = n.before;
	r.push(-1);
	let c = t.createTracker(n);
	for (; ++o < i.length;) {
		let l = i[o], u;
		if (r[r.length - 1] = o, o + 1 < i.length) {
			let n = t.handle.handlers[i[o + 1].type];
			n && n.peek && (n = n.peek), u = n ? Lr(n(i[o + 1], e, t, {
				before: "",
				after: "",
				...c.current()
			})) : "";
		} else u = n.after;
		let d = a[a.length - 1];
		typeof d == "string" && (s === "\r" || s === "\n") && l.type === "html" && Vr(l.value) !== 7 && (a[a.length - 1] = d.replace(/(\r?\n|\r)$/, " "), s = " ", c = t.createTracker(n), c.move($r(a, n.before, n.after)));
		let f = t.handle.handlers[l.type];
		if (f && f.attention) {
			let e = Zr(l, f.attention, t, c);
			a.push(e), s = Rr(e.sequences[0]);
		} else {
			let n = t.handle(l, e, t, {
				...c.current(),
				after: u,
				before: s
			});
			if (!n) continue;
			c.move(n), a.push(n), s = Rr(n);
		}
	}
	return r.pop(), a;
}
function Zr(e, t, n, r) {
	let { construct: i, markers: a, sizes: o } = t(e, n), s = Yr(e.type, a, o), c = s[0], l = n.enter(i);
	r.move(c);
	let u = Xr(e, n, {
		...r.current(),
		before: c,
		after: c
	});
	return r.move($r(u, c, c)), r.move(c), l(), {
		children: u,
		sequences: s
	};
}
function Qr(e, t, n, r) {
	let i = [], a, o = Rr(n), s = -1;
	for (; ++s < e.length;) {
		let n = e[s];
		if (typeof n == "string") {
			let e = {
				value: n,
				attention: void 0
			};
			a && a === Lr(n) && Kr([e], !0), a = void 0, i.push(e), e.value && (o = Rr(e.value));
			continue;
		}
		let c = t.get(n) || n.sequences[0], l = c.charAt(0), u = e[s + 1], d = o, f = Lr(u === void 0 ? r : typeof u == "string" ? u : t.get(u) || u.sequences[0]), p = Qr(n.children, t, c, c), m = p.length > 0 ? p[0].value : "", h = Br(d.charCodeAt(d.length - 1), m.charCodeAt(0), l);
		h.inside && Kr(p, !0);
		let g = p.length > 0 ? Rr(p[p.length - 1].value) : "", _ = Br(f.charCodeAt(0), g.charCodeAt(g.length - 1), l);
		_.inside && Kr(p, !1), h.outside && d !== "\n" && d !== "\r" && Kr(i, !1), _.outside && (a = f), i.push({
			value: c,
			attention: n
		}, ...p, {
			value: c,
			attention: n
		}), o = c.charAt(c.length - 1);
	}
	return i;
}
function $r(e, t, n) {
	let r = /* @__PURE__ */ new Map(), i = Qr(e, r, t, n), a = i, o = Wr(a, t, n);
	for (; o;) {
		let s = qr(e, r, o, t, n);
		if (!s) {
			a = i;
			break;
		}
		r = s.chosen, a = s.tokens, o = s.mistake;
	}
	let s = "";
	for (let e of a) s += e.value;
	return s;
}
ei.attention = ti, ei.peek = ni;
function ei(e, t, n, r) {
	let i = n.enter("phrasing"), a = $r([Zr(e, ti, n, n.createTracker(r))], r.before, r.after);
	return i(), a;
}
function ti(e, t) {
	return {
		construct: "emphasis",
		markers: Ir(t) === "*" ? ["*", "_"] : ["_", "*"],
		sizes: [1]
	};
}
function ni(e, t, n) {
	return n.options.emphasis || "*";
}
// node_modules/.pnpm/unist-util-visit-parents@6.1.0/node_modules/unist-util-visit-parents/lib/color.js
function ri(e) {
	return e;
}
// node_modules/.pnpm/unist-util-visit-parents@6.1.0/node_modules/unist-util-visit-parents/lib/index.js
var ii = [], ai = "skip";
function oi(e, t, n, r) {
	if (!e || typeof e != "object") throw Error("Unexpected value `" + e + "` for `tree`, expected node");
	let i = !0, a, o, s, c;
	if (typeof n == "function" || n && typeof n == "object" ? (a = t, o = n, r && (i = !1)) : (o = t, n && (i = !1)), typeof o == "function") s = o;
	else if (o && typeof o == "object") {
		if (o.enter !== null && o.enter !== void 0) {
			if (typeof o.enter == "function") s = o.enter;
			else throw Error("Unexpected value `" + o.enter + "` for `visitors.enter`, expected optional visitor");
		}
		if (typeof o.exit == "function") c = o.exit;
		else throw Error("Unexpected value `" + o.exit + "` for `visitors.exit`, expected visitor (pass a visitor directly if you only want an enter visitor)");
	} else throw Error("Unexpected value `" + o + "` for `enter` or `visitors`, expected visitor or object with visitors");
	let l = ur(a), u = i ? 1 : -1;
	d(e, void 0, [])();
	function d(e, t, n) {
		let r = e && typeof e == "object" ? e : {};
		if (typeof r.type == "string") {
			let t = typeof r.tagName == "string" ? r.tagName : typeof r.name == "string" ? r.name : void 0;
			Object.defineProperty(o, "name", { value: "node (" + ri(e.type + (t ? "<" + t + ">" : "")) + ")" });
		}
		return o;
		function o() {
			let r = !a || l(e, t, n[n.length - 1] || void 0), o = ii;
			if (s && r && (o = si(s(e, n, t)), o[0] === !1)) return o;
			if ("children" in e && e.children) {
				let t = e;
				if (t.children && o[0] !== "skip") {
					let e = (i ? -1 : t.children.length) + u, r = [...n, t];
					for (; e > -1 && e < t.children.length;) {
						let n = t.children[e], i = d(n, e, r)();
						if (i[0] === !1) return i;
						e = typeof i[1] == "number" ? i[1] : e + u;
					}
				}
			}
			if (c && r) {
				let r = si(c(e, n, t));
				if (r[0] === "skip") throw Error("Unexpected value `" + r[0] + "` from `exit`, expected `CONTINUE`, `EXIT`, index, or nothing");
				if (r[0] === !1 || typeof r[1] == "number") return r;
			}
			return o;
		}
	}
}
function si(e) {
	return Array.isArray(e) ? e : typeof e == "number" ? [!0, e] : e == null ? ii : [e];
}
// node_modules/.pnpm/unist-util-visit@5.2.0/node_modules/unist-util-visit/lib/index.js
function ci(e, t, n, r) {
	let i, a, o;
	typeof n == "function" || n && typeof n == "object" ? (i = t, a = n, o = r) : (a = t, o = n), typeof a == "function" ? oi(e, i, ui(a), o) : oi(e, i, li(a), o);
}
function li(e) {
	return !e || typeof e != "object" ? e : {
		enter: typeof e.enter == "function" ? ui(e.enter) : e.enter,
		exit: typeof e.exit == "function" ? ui(e.exit) : e.exit
	};
}
function ui(e) {
	return t;
	function t(t, n, r) {
		return e(t, r, n[n.length - 1]);
	}
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/format-heading-as-setext.js
function di(e, t) {
	let n = !1;
	return ci(e, function(e) {
		if ("value" in e && /\r?\n|\r/.test(e.value) || e.type === "break") return n = !0, !1;
	}), !!((!e.depth || e.depth < 3) && de(e) && (t.options.setext || n));
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/heading.js
function fi(e, t, n, r) {
	let i = Math.max(Math.min(6, e.depth || 1), 1), a = n.createTracker(r);
	if (di(e, n)) {
		let t = n.enter("headingSetext"), r = n.enter("phrasing"), o = n.containerPhrasing(e, {
			...a.current(),
			before: "\n",
			after: "\n"
		});
		return r(), t(), o + "\n" + (i === 1 ? "=" : "-").repeat(o.length - (Math.max(o.lastIndexOf("\r"), o.lastIndexOf("\n")) + 1));
	}
	let o = "#".repeat(i), s = n.enter("headingAtx"), c = n.enter("phrasing");
	a.move(o + " ");
	let l = n.containerPhrasing(e, {
		before: "# ",
		after: "\n",
		...a.current()
	}), u = l.charCodeAt(0);
	return (u === 9 || u === 32) && (l = zr(u) + l.slice(1)), l = l ? o + " " + l : o, n.options.closeAtx && (l += " " + o), c(), s(), l;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/html.js
pi.peek = mi;
function pi(e) {
	return e.value || "";
}
function mi() {
	return "<";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/image.js
hi.peek = gi;
function hi(e, t, n, r) {
	let i = Pr(n), a = i === "\"" ? "Quote" : "Apostrophe", o = n.enter("image"), s = n.enter("label"), c = n.createTracker(r), l = c.move("![");
	return l += c.move(n.safe(e.alt, {
		before: l,
		after: "]",
		...c.current()
	})), l += c.move("]("), s(), !e.url && e.title || /[\0- \u007F]/.test(e.url) ? (s = n.enter("destinationLiteral"), l += c.move("<"), l += c.move(n.safe(e.url, {
		before: l,
		after: ">",
		...c.current()
	})), l += c.move(">")) : (s = n.enter("destinationRaw"), l += c.move(n.safe(e.url, {
		before: l,
		after: e.title ? " " : ")",
		...c.current()
	}))), s(), e.title && (s = n.enter(`title${a}`), l += c.move(" " + i), l += c.move(n.safe(e.title, {
		before: l,
		after: i,
		...c.current()
	})), l += c.move(i), s()), l += c.move(")"), o(), l;
}
function gi() {
	return "!";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/image-reference.js
_i.peek = vi;
function _i(e, t, n, r) {
	let i = e.referenceType, a = n.enter("imageReference"), o = n.enter("label"), s = n.createTracker(r), c = s.move("!["), l = n.safe(e.alt, {
		before: c,
		after: "]",
		...s.current()
	});
	c += s.move(l + "]["), o();
	let u = n.stack;
	n.stack = u.filter(function(e) {
		return e !== "phrasing";
	}), o = n.enter("reference");
	let d = n.safe(n.associationId(e), {
		before: c,
		after: "]",
		...s.current()
	});
	return o(), n.stack = u, a(), i === "full" || !l || l !== d ? c += s.move(d + "]") : i === "shortcut" ? c = c.slice(0, -1) : c += s.move("]"), c;
}
function vi() {
	return "!";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/inline-code.js
yi.peek = bi;
function yi(e, t, n) {
	let r = e.value || "", i = "`", a = -1;
	for (; RegExp("(^|[^`])" + i + "([^`]|$)").test(r);) i += "`";
	for (/[^\n\r ]/.test(r) && (/^[\n\r ]/.test(r) && /[\n\r ]$/.test(r) || /^`|`$/.test(r)) && (r = " " + r + " "); ++a < n.unsafe.length;) {
		let e = n.unsafe[a], t = n.compilePattern(e);
		if (!e.atBreak) continue;
		let i;
		for (; i = t.exec(r);) {
			let e = i.index;
			r.charCodeAt(e) === 10 && r.charCodeAt(e - 1) === 13 && e--, r = r.slice(0, e) + " " + r.slice(i.index + 1);
		}
	}
	return i + r + i;
}
function bi() {
	return "`";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/format-link-as-autolink.js
function xi(e, t) {
	let n = de(e);
	return !(t.options.resourceLink || !e.url || e.title || !e.children || e.children.length !== 1 || e.children[0].type !== "text" || n !== e.url && "mailto:" + n !== e.url || !/^[a-z][+\-.a-z]+:/i.test(e.url) || /[\0- <>\u007F]/.test(e.url));
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/link.js
Si.peek = Ci;
function Si(e, t, n, r) {
	let i = Pr(n), a = i === "\"" ? "Quote" : "Apostrophe", o = n.createTracker(r);
	if (xi(e, n)) {
		let t = n.stack;
		n.stack = t.filter(function(e) {
			return e !== "phrasing";
		});
		let r = n.enter("autolink"), i = o.move("<");
		return i += o.move(n.containerPhrasing(e, {
			before: i,
			after: ">",
			...o.current()
		})), i += o.move(">"), r(), n.stack = t, i;
	}
	let s = n.enter("link"), c = n.enter("label"), l = o.move("["), u = n.containerPhrasing(e, {
		before: l,
		after: "](",
		...o.current()
	});
	l += o.move(u), c(), l += o.move("](");
	let d = /[\0- \u007F]/.test(e.url), f = r.before.charAt(r.before.length - 1), p = e.title || void 0;
	if (!p && !d && (f === "\n" || f === "\r")) {
		let e = 0;
		for (; e < u.length && u.charCodeAt(e) !== 93;) e += u.charCodeAt(e) === 92 ? 2 : 1;
		u.charCodeAt(e + 1) === 58 && (p = "");
	}
	return !e.url && typeof p == "string" || d ? (c = n.enter("destinationLiteral"), l += o.move("<"), l += o.move(n.safe(e.url, {
		before: l,
		after: ">",
		...o.current()
	})), l += o.move(">")) : (c = n.enter("destinationRaw"), l += o.move(n.safe(e.url, {
		before: l,
		after: typeof p == "string" ? " " : ")",
		...o.current()
	}))), c(), typeof p == "string" && (c = n.enter(`title${a}`), l += o.move(" " + i), l += o.move(n.safe(p, {
		before: l,
		after: i,
		...o.current()
	})), l += o.move(i), c()), l += o.move(")"), s(), l;
}
function Ci(e, t, n) {
	return xi(e, n) ? "<" : "[";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/link-reference.js
wi.peek = Ti;
function wi(e, t, n, r) {
	let i = e.referenceType, a = n.enter("linkReference"), o = n.enter("label"), s = n.createTracker(r), c = s.move("["), l = n.containerPhrasing(e, {
		before: c,
		after: "]",
		...s.current()
	});
	c += s.move(l + "]["), o();
	let u = n.stack;
	n.stack = u.filter(function(e) {
		return e !== "phrasing";
	}), o = n.enter("reference");
	let d = n.safe(n.associationId(e), {
		before: c,
		after: "]",
		...s.current()
	});
	return o(), n.stack = u, a(), i === "full" || !l || l !== d ? c += s.move(d + "]") : i === "shortcut" ? c = c.slice(0, -1) : c += s.move("]"), c;
}
function Ti() {
	return "[";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-bullet.js
function Ei(e) {
	let t = e.options.bullet || "*";
	if (t !== "*" && t !== "+" && t !== "-") throw Error("Cannot serialize items with `" + t + "` for `options.bullet`, expected `*`, `+`, or `-`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-bullet-other.js
function Di(e) {
	let t = Ei(e), n = e.options.bulletOther;
	if (!n) return t === "*" ? "-" : "*";
	if (n !== "*" && n !== "+" && n !== "-") throw Error("Cannot serialize items with `" + n + "` for `options.bulletOther`, expected `*`, `+`, or `-`");
	if (n === t) throw Error("Expected `bullet` (`" + t + "`) and `bulletOther` (`" + n + "`) to be different");
	return n;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-bullet-ordered.js
function Oi(e) {
	let t = e.options.bulletOrdered || ".";
	if (t !== "." && t !== ")") throw Error("Cannot serialize items with `" + t + "` for `options.bulletOrdered`, expected `.` or `)`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-rule.js
function ki(e) {
	let t = e.options.rule || "*";
	if (t !== "*" && t !== "-" && t !== "_") throw Error("Cannot serialize rules with `" + t + "` for `options.rule`, expected `*`, `-`, or `_`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/list.js
function Ai(e, t, n, r) {
	let i = n.enter("list"), a = n.bulletCurrent, o = e.ordered ? Oi(n) : Ei(n), s = e.ordered ? o === "." ? ")" : "." : Di(n), c = t && n.bulletLastUsed ? o === n.bulletLastUsed : !1;
	if (!e.ordered) {
		let t = e.children ? e.children[0] : void 0;
		if (o !== "*" && o !== "-" || !t || t.children && t.children[0] || n.stack[n.stack.length - 1] !== "list" || n.stack[n.stack.length - 2] !== "listItem" || n.stack[n.stack.length - 3] !== "list" || n.stack[n.stack.length - 4] !== "listItem" || n.indexStack[n.indexStack.length - 1] !== 0 || n.indexStack[n.indexStack.length - 2] !== 0 || n.indexStack[n.indexStack.length - 3] !== 0 || (c = !0), ki(n) === o && t) {
			let t = -1;
			for (; ++t < e.children.length;) {
				let n = e.children[t];
				if (n && n.type === "listItem" && n.children && n.children[0] && n.children[0].type === "thematicBreak") {
					c = !0;
					break;
				}
			}
		}
	}
	c && (o = s), n.bulletCurrent = o;
	let l = n.containerFlow(e, r);
	return n.bulletLastUsed = o, n.bulletCurrent = a, i(), l;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-list-item-indent.js
function ji(e) {
	let t = e.options.listItemIndent || "one";
	if (t !== "tab" && t !== "one" && t !== "mixed") throw Error("Cannot serialize items with `" + t + "` for `options.listItemIndent`, expected `tab`, `one`, or `mixed`");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/list-item.js
function Mi(e, t, n, r) {
	let i = ji(n), a = n.bulletCurrent || Ei(n);
	t && t.type === "list" && t.ordered && (a = (typeof t.start == "number" && t.start > -1 ? t.start : 1) + (n.options.incrementListMarker === !1 ? 0 : t.children.indexOf(e)) + a);
	let o = a.length + 1;
	(i === "tab" || i === "mixed" && (t && t.type === "list" && t.spread || e.spread)) && (o = Math.ceil(o / 4) * 4);
	let s = n.createTracker(r);
	s.move(a + " ".repeat(o - a.length)), s.shift(o);
	let c = n.enter("listItem"), l = n.indentLines(n.containerFlow(e, s.current()), u);
	return c(), l;
	function u(e, t, n) {
		return t ? (n ? "" : " ".repeat(o)) + e : (n ? a : a + " ".repeat(o - a.length)) + e;
	}
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/paragraph.js
function Ni(e, t, n, r) {
	let i = n.enter("paragraph"), a = n.enter("phrasing"), o = n.containerPhrasing(e, r);
	return a(), i(), o;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/root.js
function Pi(e, t, n, r) {
	if (!e.children.some(function(e) {
		return _r(e);
	})) return n.containerFlow(e, r);
	let i = n.enter("phrasing"), a = n.containerPhrasing(e, r);
	return i(), a;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-strong.js
function Fi(e) {
	let t = e.options.strong || "*";
	if (t !== "*" && t !== "_") throw Error("Cannot serialize strong with `" + t + "` for `options.strong`, expected `*`, or `_`");
	return t;
}
Ii.attention = Li, Ii.peek = Ri;
function Ii(e, t, n, r) {
	let i = n.enter("phrasing"), a = $r([Zr(e, Li, n, n.createTracker(r))], r.before, r.after);
	return i(), a;
}
function Li(e, t) {
	return {
		construct: "strong",
		markers: Fi(t) === "*" ? ["*", "_"] : ["_", "*"],
		sizes: [2]
	};
}
function Ri(e, t, n) {
	return n.options.strong || "*";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/text.js
function zi(e, t, n, r) {
	/* c8 ignore next -- parent always passed */
	let i = t ? t.children : [], a = n.indexStack[n.indexStack.length - 1];
	return n.safe(e.value, {
		...r,
		afterNode: i[a + 1],
		beforeNode: i[a - 1]
	});
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/check-rule-repetition.js
function Bi(e) {
	let t = e.options.ruleRepetition || 3;
	if (t < 3) throw Error("Cannot serialize rules with repetition `" + t + "` for `options.ruleRepetition`, expected `3` or more");
	return t;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/thematic-break.js
function Vi(e, t, n) {
	let r = (ki(n) + (n.options.ruleSpaces ? " " : "")).repeat(Bi(n));
	return n.options.ruleSpaces ? r.slice(0, -1) : r;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/handle/index.js
var Hi = {
	blockquote: wr,
	break: Or,
	code: Mr,
	definition: Fr,
	emphasis: ei,
	hardBreak: Or,
	heading: fi,
	html: pi,
	image: hi,
	imageReference: _i,
	inlineCode: yi,
	link: Si,
	linkReference: wi,
	list: Ai,
	listItem: Mi,
	paragraph: Ni,
	root: Pi,
	strong: Ii,
	text: zi,
	thematicBreak: Vi
}, Ui = [Wi];
function Wi(e, t, n, r) {
	if (t.type === "code" && Ar(t, r) && (e.type === "list" || e.type === t.type && Ar(e, r))) return !1;
	if ("spread" in n && typeof n.spread == "boolean") {
		if (n.spread) return 1;
		if (e.type === "html") {
			let t = Vr(e.value);
			if (t === void 0 || t === 6 || t === 7) return 1;
		}
		if (e.type === "blockquote" && t.type === "blockquote" || (e.type === "definition" || e.type === "paragraph") && t.type === "list" && (t.ordered && typeof t.start == "number" && t.start !== 1 || t.children.length > 0 && t.children[0].children.length === 0) || e.type === "paragraph" && t.type === "thematicBreak" && ki(r) === "-" && !r.options.ruleSpaces) return 1;
		let i = e;
		for (; (i.type === "blockquote" || i.type === "list" || i.type === "listItem") && i.children.length > 0;) i = i.children[i.children.length - 1];
		if (i.type !== "definition" && i.type !== "paragraph") return 0;
		if (t.type === "code" && Ar(t, r)) return 1;
		if (t.type === "html") {
			let e = Vr(t.value);
			if (e === void 0 || e === 7) return 1;
		}
		return (e.type === "paragraph" || e !== i) && (t.type === "definition" || t.type === "heading" && di(t, r) || t.type === "paragraph") ? 1 : 0;
	}
}
for (var Gi = [
	"autolink",
	"destinationLiteral",
	"destinationRaw",
	"reference",
	"titleQuote",
	"titleApostrophe"
], Ki = [
	{
		character: "	",
		after: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: "	",
		before: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: "	",
		inConstruct: ["codeFencedLangGraveAccent", "codeFencedLangTilde"]
	},
	{
		character: "\r",
		inConstruct: [
			"codeFencedLangGraveAccent",
			"codeFencedLangTilde",
			"codeFencedMetaGraveAccent",
			"codeFencedMetaTilde",
			"destinationLiteral",
			"headingAtx"
		]
	},
	{
		character: "\n",
		inConstruct: [
			"codeFencedLangGraveAccent",
			"codeFencedLangTilde",
			"codeFencedMetaGraveAccent",
			"codeFencedMetaTilde",
			"destinationLiteral",
			"headingAtx"
		]
	},
	{
		character: "\r",
		before: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: "\r",
		after: "\\r",
		inConstruct: "phrasing"
	},
	{
		character: "\n",
		before: "\\n",
		inConstruct: "phrasing"
	},
	{
		character: "\n",
		after: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: " ",
		after: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: " ",
		before: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: " ",
		inConstruct: ["codeFencedLangGraveAccent", "codeFencedLangTilde"]
	},
	{
		character: "!",
		after: "\\[",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		character: "\"",
		inConstruct: "titleQuote"
	},
	{
		atBreak: !0,
		character: "#"
	},
	{
		character: "#",
		inConstruct: "headingAtx",
		after: "(?:[\r\n]|$)"
	},
	{
		character: "&",
		after: "[#A-Za-z]",
		inConstruct: [
			"codeFencedLangGraveAccent",
			"codeFencedLangTilde",
			"codeFencedMetaGraveAccent",
			"codeFencedMetaTilde",
			"destinationLiteral",
			"destinationRaw",
			"phrasing",
			"titleApostrophe",
			"titleQuote"
		]
	},
	{
		character: "'",
		inConstruct: "titleApostrophe"
	},
	{
		character: "(",
		inConstruct: "destinationRaw"
	},
	{
		before: "\\]",
		character: "(",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		atBreak: !0,
		before: "\\d+",
		character: ")"
	},
	{
		character: ")",
		inConstruct: "destinationRaw"
	},
	{
		atBreak: !0,
		character: "*",
		after: "(?:[ 	\r\n*])"
	},
	{
		character: "*",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		atBreak: !0,
		character: "+",
		after: "(?:[ 	\r\n])"
	},
	{
		atBreak: !0,
		character: "-",
		after: "(?:[ 	\r\n-])"
	},
	{
		atBreak: !0,
		before: "\\d+",
		character: ".",
		after: "(?:[ 	\r\n]|$)"
	},
	{
		atBreak: !0,
		character: "<",
		after: "[!/?A-Za-z]"
	},
	{
		character: "<",
		after: "[!/?A-Za-z]",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		character: "<",
		inConstruct: "destinationLiteral"
	},
	{
		atBreak: !0,
		character: "="
	},
	{
		atBreak: !0,
		character: ">"
	},
	{
		character: ">",
		inConstruct: "destinationLiteral"
	},
	{
		atBreak: !0,
		character: "["
	},
	{
		character: "[",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		character: "[",
		inConstruct: ["label", "reference"]
	},
	{
		character: "\\",
		after: "[\\r\\n]",
		inConstruct: "phrasing"
	},
	{
		character: "]",
		inConstruct: ["label", "reference"]
	},
	{
		atBreak: !0,
		character: "_"
	},
	{
		character: "_",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		atBreak: !0,
		character: "`"
	},
	{
		character: "`",
		inConstruct: ["codeFencedLangGraveAccent", "codeFencedMetaGraveAccent"]
	},
	{
		character: "`",
		inConstruct: "phrasing",
		notInConstruct: Gi
	},
	{
		atBreak: !0,
		character: "~"
	},
	{
		character: "<",
		inConstruct: "autolink"
	},
	{
		character: ">",
		inConstruct: "autolink"
	},
	{
		character: "",
		inConstruct: "autolink"
	}
], qi = -1; ++qi < 33;) Ki.push({
	character: String.fromCharCode(qi),
	inConstruct: "autolink"
});
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/association.js
function Ji(e) {
	return e.label || !e.identifier ? e.label || "" : Xn(e.identifier);
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/compile-pattern.js
function Yi(e) {
	if (!e._compiled) {
		let t = (e.atBreak ? "[\\r\\n][\\t ]*" : "") + (e.before ? "(?:" + e.before + ")" : "");
		e._compiled = RegExp((e.beforeNode ? "^" : t ? "(" + t + ")" : "") + (/[$()*+.?[\\\]^{|}]/.test(e.character) ? "\\" : "") + e.character + (e.afterNode ? "$" : e.after ? "(?:" + e.after + ")" : ""), e.unicode ? "gu" : "g");
	}
	return e._compiled;
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/container-flow.js
function Xi(e, t, n) {
	let r = t.indexStack, i = e.children || [], a = t.createTracker(n), o = [], s = -1;
	for (r.push(-1); ++s < i.length;) {
		let n = i[s];
		r[r.length - 1] = s, o.push(a.move(t.handle(n, e, t, {
			before: "\n",
			after: "\n",
			...a.current()
		}))), n.type !== "list" && (t.bulletLastUsed = void 0), s < i.length - 1 && o.push(a.move(Zi(n, i[s + 1], e, t)));
	}
	return r.pop(), o.join("");
}
function Zi(e, t, n, r) {
	let i = r.join.length;
	for (; i--;) {
		let a = r.join[i](e, t, n, r);
		if (a === !0 || a === 1) break;
		if (typeof a == "number") return "\n".repeat(1 + a);
		if (a === !1) return "\n\n<!---->\n\n";
	}
	return "\n\n";
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/indent-lines.js
var Qi = /\r?\n|\r/g;
function $i(e, t) {
	let n = [], r = 0, i = 0, a;
	for (; a = Qi.exec(e);) o(e.slice(r, a.index)), n.push(a[0]), r = a.index + a[0].length, i++;
	return o(e.slice(r)), n.join("");
	function o(e) {
		n.push(t(e, i, !e));
	}
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/safe.js
var ea = {}.hasOwnProperty;
function ta(e, t, n) {
	let r = (n.before || "") + (t || "") + (n.after || ""), i = [], a = [], o = {}, s = e.stack.includes("autolink"), c = n.before ? n.before.length : 0, l = r.length - (n.after ? n.after.length : 0), u = -1;
	for (; ++u < e.unsafe.length;) {
		let t = e.unsafe[u];
		if (!Er(e.stack, t) || !na(t.beforeNode, n.beforeNode) || !na(t.afterNode, n.afterNode)) continue;
		let a = e.compilePattern(t), s = t.beforeNode ? c : 0, d = r.slice(s, t.afterNode ? l : void 0), f;
		for (; f = a.exec(d);) {
			let e = "before" in t || !!t.atBreak, n = "after" in t, r = s + f.index + (e ? f[1].length : 0);
			ea.call(o, r) ? (o[r].before && !e && (o[r].before = !1), o[r].after && !n && (o[r].after = !1)) : (i.push(r), o[r] = {
				before: e,
				after: n
			});
		}
	}
	i.sort(ra);
	let d = c;
	for (u = -1; ++u < i.length;) {
		let e = i[u];
		if (!(e < d || e >= l)) {
			if (r.charAt(e) === "_" && e > 0 && He(r.charCodeAt(e - 1)) === void 0 && !ea.call(o, e - 1) && !(e - 1 === c && /[*_]/.test(r.charAt(c - 1)))) {
				let t = e + 1;
				for (; t < l && r.charAt(t) === "_";) t++;
				let n = t - e - 1;
				if (i[u + n] === t - 1 && t < r.length && He(r.charCodeAt(t)) === void 0 && !ea.call(o, t) && !(t === l - 1 && /[*_]/.test(r.charAt(l)))) {
					u += n;
					continue;
				}
			}
			if (!(e + 1 < l && i[u + 1] === e + 1 && o[e].after && !o[e + 1].before && !o[e + 1].after || e - 1 >= c && i[u - 1] === e - 1 && o[e].before && !o[e - 1].before && !o[e - 1].after)) {
				if (d !== e) {
					let t = r.slice(d, e);
					a.push(s ? t : ia(t, "\\"));
				}
				d = e + 1, s ? a.push("%" + r.charCodeAt(e).toString(16).toUpperCase().padStart(2, "0")) : /[!-/:-@[-`{-~]/.test(r.charAt(e)) && (!n.encode || !n.encode.includes(r.charAt(e))) ? a.push("\\" + r.charAt(e)) : a.push(zr(r.charCodeAt(e)));
			}
		}
	}
	let f = r.slice(d, l);
	return a.push(s ? f : ia(f, n.after)), a.join("");
}
function na(e, t) {
	return e ? t ? typeof e == "string" ? e === t.type : e.includes(t.type) : !1 : !0;
}
function ra(e, t) {
	return e - t;
}
function ia(e, t) {
	let n = /\\(?=[!-/:-@[-`{-~])/g, r = [], i = [], a = e + t, o = -1, s = 0, c;
	for (; c = n.exec(a);) r.push(c.index);
	for (; ++o < r.length;) s !== r[o] && i.push(e.slice(s, r[o])), i.push("\\"), s = r[o];
	return i.push(e.slice(s)), i.join("");
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/util/track.js
function aa(e) {
	/* c8 ignore next 5 */
	let t = e || {}, n = t.now || {}, r = t.lineShift || 0, i = n.line || 1, a = n.column || 1;
	return {
		move: c,
		current: o,
		shift: s
	};
	function o() {
		return {
			now: {
				line: i,
				column: a
			},
			lineShift: r
		};
	}
	function s(e) {
		r += e;
	}
	function c(e) {
		let t = e || "", n = t.split(/\r?\n|\r/g), o = n[n.length - 1];
		return i += n.length - 1, a = n.length === 1 ? a + o.length : 1 + o.length + r, t;
	}
}
// node_modules/.pnpm/mdast-util-to-markdown@2.2.1/node_modules/mdast-util-to-markdown/lib/index.js
function oa(e, t) {
	let n = t || {}, r = {
		associationId: Ji,
		bulletCurrent: void 0,
		bulletLastUsed: void 0,
		containerPhrasing: ua,
		containerFlow: da,
		createTracker: aa,
		compilePattern: Yi,
		enter: a,
		handlers: { ...Hi },
		handle: void 0,
		indentLines: $i,
		indexStack: [],
		join: [...Ui],
		options: {},
		safe: fa,
		stack: [],
		unsafe: [...Ki]
	};
	xr(r, n), r.options.tightDefinitions && r.join.push(la), r.handle = yr("type", {
		invalid: sa,
		unknown: ca,
		handlers: r.handlers
	});
	let i = r.handle(_r(e) ? {
		type: "root",
		children: [e]
	} : e, void 0, r, {
		before: "\n",
		after: "\n",
		now: {
			line: 1,
			column: 1
		},
		lineShift: 0
	});
	return i && i.charCodeAt(i.length - 1) !== 10 && i.charCodeAt(i.length - 1) !== 13 && (i += "\n"), i;
	function a(e) {
		return r.stack.push(e), t;
		function t() {
			r.stack.pop();
		}
	}
}
function sa(e) {
	throw Error("Cannot handle value `" + e + "`, expected node");
}
function ca(e) {
	throw Error("Cannot handle unknown node `" + e.type + "`");
}
function la(e, t) {
	if (e.type === "definition" && e.type === t.type) return 0;
}
function ua(e, t) {
	return Gr(e, this, t);
}
function da(e, t) {
	return Xi(e, this, t);
}
function fa(e, t) {
	return ta(this, e, t);
}
// node_modules/.pnpm/remark-stringify@11.0.0/node_modules/remark-stringify/lib/index.js
function pa(e) {
	let t = this;
	t.compiler = n;
	function n(n) {
		return oa(n, {
			...t.data("settings"),
			...e,
			extensions: t.data("toMarkdownExtensions") || []
		});
	}
}
// node_modules/.pnpm/bail@2.0.2/node_modules/bail/index.js
function ma(e) {
	if (e) throw e;
}
// node_modules/.pnpm/devlop@1.1.0/node_modules/devlop/lib/default.js
var ha = /* @__PURE__ */ e((/* @__PURE__ */ n(((e, t) => {
	var n = Object.prototype.hasOwnProperty, r = Object.prototype.toString, i = Object.defineProperty, a = Object.getOwnPropertyDescriptor, o = function(e) {
		return typeof Array.isArray == "function" ? Array.isArray(e) : r.call(e) === "[object Array]";
	}, s = function(e) {
		if (!e || r.call(e) !== "[object Object]") return !1;
		var t = n.call(e, "constructor"), i = e.constructor && e.constructor.prototype && n.call(e.constructor.prototype, "isPrototypeOf");
		if (e.constructor && !t && !i) return !1;
		for (var a in e);
		return a === void 0 || n.call(e, a);
	}, c = function(e, t) {
		i && t.name === "__proto__" ? i(e, t.name, {
			enumerable: !0,
			configurable: !0,
			value: t.newValue,
			writable: !0
		}) : e[t.name] = t.newValue;
	}, l = function(e, t) {
		if (t === "__proto__") {
			if (!n.call(e, t)) return;
			if (a) return a(e, t).value;
		}
		return e[t];
	};
	t.exports = function e() {
		var t, n, r, i, a, u, d = arguments[0], f = 1, p = arguments.length, m = !1;
		for (typeof d == "boolean" && (m = d, d = arguments[1] || {}, f = 2), (d == null || typeof d != "object" && typeof d != "function") && (d = {}); f < p; ++f) if (t = arguments[f], t != null) for (n in t) r = l(d, n), i = l(t, n), d !== i && (m && i && (s(i) || (a = o(i))) ? (a ? (a = !1, u = r && o(r) ? r : []) : u = r && s(r) ? r : {}, c(d, {
			name: n,
			newValue: e(m, u, i)
		})) : i !== void 0 && c(d, {
			name: n,
			newValue: i
		}));
		return d;
	};
})))(), 1);
function ga() {}
// node_modules/.pnpm/is-plain-obj@4.1.0/node_modules/is-plain-obj/index.js
function _a(e) {
	if (typeof e != "object" || !e) return !1;
	let t = Object.getPrototypeOf(e);
	return (t === null || t === Object.prototype || Object.getPrototypeOf(t) === null) && !(Symbol.toStringTag in e) && !(Symbol.iterator in e);
}
// node_modules/.pnpm/trough@2.2.0/node_modules/trough/lib/index.js
function va() {
	let e = [], t = {
		run: n,
		use: r
	};
	return t;
	function n(...t) {
		let n = -1, r = t.pop();
		if (typeof r != "function") throw TypeError("Expected function as last argument, not " + r);
		i(null, ...t);
		function i(a, ...o) {
			let s = e[++n], c = -1;
			if (a) r(a);
			else {
				for (; ++c < t.length;) (o[c] === null || o[c] === void 0) && (o[c] = t[c]);
				t = o, s ? ya(s, i)(...o) : r(null, ...o);
			}
		}
	}
	function r(n) {
		if (typeof n != "function") throw TypeError("Expected `middelware` to be a function, not " + n);
		return e.push(n), t;
	}
}
function ya(e, t) {
	let n;
	return r;
	function r(...t) {
		let r = e.length > t.length, o;
		r && t.push(i);
		try {
			o = e.apply(this, t);
		} catch (e) {
			let t = e;
			if (r && n) throw t;
			return i(t);
		}
		r || (o && o.then && typeof o.then == "function" ? o.then(a, i) : o instanceof Error ? i(o) : a(o));
	}
	function i(e, ...r) {
		n || (n = !0, t(e, ...r));
	}
	function a(e) {
		i(null, e);
	}
}
// node_modules/.pnpm/vfile-message@4.0.3/node_modules/vfile-message/lib/index.js
var P = class extends Error {
	constructor(e, t, n) {
		super(), typeof t == "string" && (n = t, t = void 0);
		let r = "", i = {}, a = !1;
		if (t && (i = "line" in t && "column" in t || "start" in t && "end" in t ? { place: t } : "type" in t ? {
			ancestors: [t],
			place: t.position
		} : { ...t }), typeof e == "string" ? r = e : !i.cause && e && (a = !0, r = e.message, i.cause = e), !i.ruleId && !i.source && typeof n == "string") {
			let e = n.indexOf(":");
			e === -1 ? i.ruleId = n : (i.source = n.slice(0, e), i.ruleId = n.slice(e + 1));
		}
		if (!i.place && i.ancestors && i.ancestors) {
			let e = i.ancestors[i.ancestors.length - 1];
			e && (i.place = e.position);
		}
		let o = i.place && "start" in i.place ? i.place.start : i.place;
		this.ancestors = i.ancestors || void 0, this.cause = i.cause || void 0, this.column = o ? o.column : void 0, this.fatal = void 0, this.file = "", this.message = r, this.line = o ? o.line : void 0, this.name = Qn(i.place) || "1:1", this.place = i.place || void 0, this.reason = this.message, this.ruleId = i.ruleId || void 0, this.source = i.source || void 0, this.stack = a && i.cause && typeof i.cause.stack == "string" ? i.cause.stack : "", this.actual = void 0, this.expected = void 0, this.note = void 0, this.url = void 0;
	}
};
P.prototype.file = "", P.prototype.name = "", P.prototype.reason = "", P.prototype.message = "", P.prototype.stack = "", P.prototype.column = void 0, P.prototype.line = void 0, P.prototype.ancestors = void 0, P.prototype.cause = void 0, P.prototype.fatal = void 0, P.prototype.place = void 0, P.prototype.ruleId = void 0, P.prototype.source = void 0;
// node_modules/.pnpm/vfile@6.0.3/node_modules/vfile/lib/minpath.browser.js
var ba = {
	basename: xa,
	dirname: Sa,
	extname: Ca,
	join: wa,
	sep: "/"
};
function xa(e, t) {
	if (t !== void 0 && typeof t != "string") throw TypeError("\"ext\" argument must be a string");
	Da(e);
	let n = 0, r = -1, i = e.length, a;
	if (t === void 0 || t.length === 0 || t.length > e.length) {
		for (; i--;) if (e.codePointAt(i) === 47) {
			if (a) {
				n = i + 1;
				break;
			}
		} else r < 0 && (a = !0, r = i + 1);
		return r < 0 ? "" : e.slice(n, r);
	}
	if (t === e) return "";
	let o = -1, s = t.length - 1;
	for (; i--;) if (e.codePointAt(i) === 47) {
		if (a) {
			n = i + 1;
			break;
		}
	} else o < 0 && (a = !0, o = i + 1), s > -1 && (e.codePointAt(i) === t.codePointAt(s--) ? s < 0 && (r = i) : (s = -1, r = o));
	return n === r ? r = o : r < 0 && (r = e.length), e.slice(n, r);
}
function Sa(e) {
	if (Da(e), e.length === 0) return ".";
	let t = -1, n = e.length, r;
	for (; --n;) if (e.codePointAt(n) === 47) {
		if (r) {
			t = n;
			break;
		}
	} else r ||= !0;
	return t < 0 ? e.codePointAt(0) === 47 ? "/" : "." : t === 1 && e.codePointAt(0) === 47 ? "//" : e.slice(0, t);
}
function Ca(e) {
	Da(e);
	let t = e.length, n = -1, r = 0, i = -1, a = 0, o;
	for (; t--;) {
		let s = e.codePointAt(t);
		if (s === 47) {
			if (o) {
				r = t + 1;
				break;
			}
		} else n < 0 && (o = !0, n = t + 1), s === 46 ? i < 0 ? i = t : a !== 1 && (a = 1) : i > -1 && (a = -1);
	}
	return i < 0 || n < 0 || a === 0 || a === 1 && i === n - 1 && i === r + 1 ? "" : e.slice(i, n);
}
function wa(...e) {
	let t = -1, n;
	for (; ++t < e.length;) Da(e[t]), e[t] && (n = n === void 0 ? e[t] : n + "/" + e[t]);
	return n === void 0 ? "." : Ta(n);
}
function Ta(e) {
	Da(e);
	let t = e.codePointAt(0) === 47, n = Ea(e, !t);
	return n.length === 0 && !t && (n = "."), n.length > 0 && e.codePointAt(e.length - 1) === 47 && (n += "/"), t ? "/" + n : n;
}
function Ea(e, t) {
	let n = "", r = 0, i = -1, a = 0, o = -1, s, c;
	for (; ++o <= e.length;) {
		if (o < e.length) s = e.codePointAt(o);
		else if (s === 47) break;
		else s = 47;
		if (s === 47) {
			if (i !== o - 1 && a !== 1) {
				if (i !== o - 1 && a === 2) {
					if (n.length < 2 || r !== 2 || n.codePointAt(n.length - 1) !== 46 || n.codePointAt(n.length - 2) !== 46) {
						if (n.length > 2) {
							if (c = n.lastIndexOf("/"), c !== n.length - 1) {
								c < 0 ? (n = "", r = 0) : (n = n.slice(0, c), r = n.length - 1 - n.lastIndexOf("/")), i = o, a = 0;
								continue;
							}
						} else if (n.length > 0) {
							n = "", r = 0, i = o, a = 0;
							continue;
						}
					}
					t && (n = n.length > 0 ? n + "/.." : "..", r = 2);
				} else n.length > 0 ? n += "/" + e.slice(i + 1, o) : n = e.slice(i + 1, o), r = o - i - 1;
			}
			i = o, a = 0;
		} else s === 46 && a > -1 ? a++ : a = -1;
	}
	return n;
}
function Da(e) {
	if (typeof e != "string") throw TypeError("Path must be a string. Received " + JSON.stringify(e));
}
// node_modules/.pnpm/vfile@6.0.3/node_modules/vfile/lib/minproc.browser.js
var Oa = { cwd: ka };
function ka() {
	return "/";
}
// node_modules/.pnpm/vfile@6.0.3/node_modules/vfile/lib/minurl.shared.js
function Aa(e) {
	return !!(typeof e == "object" && e && "href" in e && e.href && "protocol" in e && e.protocol && e.auth === void 0);
}
// node_modules/.pnpm/vfile@6.0.3/node_modules/vfile/lib/minurl.browser.js
function ja(e) {
	if (typeof e == "string") e = new URL(e);
	else if (!Aa(e)) {
		let t = /* @__PURE__ */ TypeError("The \"path\" argument must be of type string or an instance of URL. Received `" + e + "`");
		throw t.code = "ERR_INVALID_ARG_TYPE", t;
	}
	if (e.protocol !== "file:") {
		let e = /* @__PURE__ */ TypeError("The URL must be of scheme file");
		throw e.code = "ERR_INVALID_URL_SCHEME", e;
	}
	return Ma(e);
}
function Ma(e) {
	if (e.hostname !== "") {
		let e = /* @__PURE__ */ TypeError("File URL host must be \"localhost\" or empty on darwin");
		throw e.code = "ERR_INVALID_FILE_URL_HOST", e;
	}
	let t = e.pathname, n = -1;
	for (; ++n < t.length;) if (t.codePointAt(n) === 37 && t.codePointAt(n + 1) === 50) {
		let e = t.codePointAt(n + 2);
		if (e === 70 || e === 102) {
			let e = /* @__PURE__ */ TypeError("File URL path must not include encoded / characters");
			throw e.code = "ERR_INVALID_FILE_URL_PATH", e;
		}
	}
	return decodeURIComponent(t);
}
// node_modules/.pnpm/vfile@6.0.3/node_modules/vfile/lib/index.js
var Na = [
	"history",
	"path",
	"basename",
	"stem",
	"extname",
	"dirname"
], Pa = class {
	constructor(e) {
		let t;
		t = e ? Aa(e) ? { path: e } : typeof e == "string" || Ra(e) ? { value: e } : e : {}, this.cwd = "cwd" in t ? "" : Oa.cwd(), this.data = {}, this.history = [], this.messages = [], this.value, this.map, this.result, this.stored;
		let n = -1;
		for (; ++n < Na.length;) {
			let e = Na[n];
			e in t && t[e] !== void 0 && t[e] !== null && (this[e] = e === "history" ? [...t[e]] : t[e]);
		}
		let r;
		for (r in t) Na.includes(r) || (this[r] = t[r]);
	}
	get basename() {
		return typeof this.path == "string" ? ba.basename(this.path) : void 0;
	}
	set basename(e) {
		Ia(e, "basename"), Fa(e, "basename"), this.path = ba.join(this.dirname || "", e);
	}
	get dirname() {
		return typeof this.path == "string" ? ba.dirname(this.path) : void 0;
	}
	set dirname(e) {
		La(this.basename, "dirname"), this.path = ba.join(e || "", this.basename);
	}
	get extname() {
		return typeof this.path == "string" ? ba.extname(this.path) : void 0;
	}
	set extname(e) {
		if (Fa(e, "extname"), La(this.dirname, "extname"), e) {
			if (e.codePointAt(0) !== 46) throw Error("`extname` must start with `.`");
			if (e.includes(".", 1)) throw Error("`extname` cannot contain multiple dots");
		}
		this.path = ba.join(this.dirname, this.stem + (e || ""));
	}
	get path() {
		return this.history[this.history.length - 1];
	}
	set path(e) {
		Aa(e) && (e = ja(e)), Ia(e, "path"), this.path !== e && this.history.push(e);
	}
	get stem() {
		return typeof this.path == "string" ? ba.basename(this.path, this.extname) : void 0;
	}
	set stem(e) {
		Ia(e, "stem"), Fa(e, "stem"), this.path = ba.join(this.dirname || "", e + (this.extname || ""));
	}
	fail(e, t, n) {
		let r = this.message(e, t, n);
		throw r.fatal = !0, r;
	}
	info(e, t, n) {
		let r = this.message(e, t, n);
		return r.fatal = void 0, r;
	}
	message(e, t, n) {
		let r = new P(e, t, n);
		return this.path && (r.name = this.path + ":" + r.name, r.file = this.path), r.fatal = !1, this.messages.push(r), r;
	}
	toString(e) {
		return this.value === void 0 ? "" : typeof this.value == "string" ? this.value : new TextDecoder(e || void 0).decode(this.value);
	}
};
function Fa(e, t) {
	if (e && e.includes(ba.sep)) throw Error("`" + t + "` cannot be a path: did not expect `" + ba.sep + "`");
}
function Ia(e, t) {
	if (!e) throw Error("`" + t + "` cannot be empty");
}
function La(e, t) {
	if (!e) throw Error("Setting `" + t + "` requires `path` to be set too");
}
function Ra(e) {
	return !!(e && typeof e == "object" && "byteLength" in e && "byteOffset" in e);
}
// node_modules/.pnpm/unified@11.0.5/node_modules/unified/lib/callable-instance.js
var za = (function(e) {
	let t = this.constructor.prototype, n = t[e], r = function() {
		return n.apply(r, arguments);
	};
	return Object.setPrototypeOf(r, t), r;
}), Ba = {}.hasOwnProperty, Va = new class e extends za {
	constructor() {
		super("copy"), this.Compiler = void 0, this.Parser = void 0, this.attachers = [], this.compiler = void 0, this.freezeIndex = -1, this.frozen = void 0, this.namespace = {}, this.parser = void 0, this.transformers = va();
	}
	copy() {
		let t = new e(), n = -1;
		for (; ++n < this.attachers.length;) {
			let e = this.attachers[n];
			t.use(...e);
		}
		return t.data((0, ha.default)(!0, {}, this.namespace)), t;
	}
	data(e, t) {
		return typeof e == "string" ? arguments.length === 2 ? (Wa("data", this.frozen), this.namespace[e] = t, this) : Ba.call(this.namespace, e) && this.namespace[e] || void 0 : e ? (Wa("data", this.frozen), this.namespace = e, this) : this.namespace;
	}
	freeze() {
		if (this.frozen) return this;
		let e = this;
		for (; ++this.freezeIndex < this.attachers.length;) {
			let [t, ...n] = this.attachers[this.freezeIndex];
			if (n[0] === !1) continue;
			n[0] === !0 && (n[0] = void 0);
			let r = t.call(e, ...n);
			typeof r == "function" && this.transformers.use(r);
		}
		return this.frozen = !0, this.freezeIndex = Infinity, this;
	}
	parse(e) {
		this.freeze();
		let t = qa(e), n = this.parser || this.Parser;
		return Ha("parse", n), n(String(t), t);
	}
	process(e, t) {
		let n = this;
		return this.freeze(), Ha("process", this.parser || this.Parser), Ua("process", this.compiler || this.Compiler), t ? r(void 0, t) : new Promise(r);
		function r(r, i) {
			let a = qa(e), o = n.parse(a);
			n.run(o, a, function(e, t, r) {
				if (e || !t || !r) return s(e);
				let i = t, a = n.stringify(i, r);
				Ya(a) ? r.value = a : r.result = a, s(e, r);
			});
			function s(e, n) {
				e || !n ? i(e) : r ? r(n) : t(void 0, n);
			}
		}
	}
	processSync(e) {
		let t = !1, n;
		return this.freeze(), Ha("processSync", this.parser || this.Parser), Ua("processSync", this.compiler || this.Compiler), this.process(e, r), Ka("processSync", "process", t), n;
		function r(e, r) {
			t = !0, ma(e), n = r;
		}
	}
	run(e, t, n) {
		Ga(e), this.freeze();
		let r = this.transformers;
		return !n && typeof t == "function" && (n = t, t = void 0), n ? i(void 0, n) : new Promise(i);
		function i(i, a) {
			let o = qa(t);
			r.run(e, o, s);
			function s(t, r, o) {
				let s = r || e;
				t ? a(t) : i ? i(s) : n(void 0, s, o);
			}
		}
	}
	runSync(e, t) {
		let n = !1, r;
		return this.run(e, t, i), Ka("runSync", "run", n), r;
		function i(e, t) {
			ma(e), r = t, n = !0;
		}
	}
	stringify(e, t) {
		this.freeze();
		let n = qa(t), r = this.compiler || this.Compiler;
		return Ua("stringify", r), Ga(e), r(e, n);
	}
	use(e, ...t) {
		let n = this.attachers, r = this.namespace;
		if (Wa("use", this.frozen), e != null) {
			if (typeof e == "function") s(e, t);
			else if (typeof e == "object") Array.isArray(e) ? o(e) : a(e);
			else throw TypeError("Expected usable value, not `" + e + "`");
		}
		return this;
		function i(e) {
			if (typeof e == "function") s(e, []);
			else if (typeof e == "object") {
				if (Array.isArray(e)) {
					let [t, ...n] = e;
					s(t, n);
				} else a(e);
			} else throw TypeError("Expected usable value, not `" + e + "`");
		}
		function a(e) {
			if (!("plugins" in e) && !("settings" in e)) throw Error("Expected usable value but received an empty preset, which is probably a mistake: presets typically come with `plugins` and sometimes with `settings`, but this has neither");
			o(e.plugins), e.settings && (r.settings = (0, ha.default)(!0, r.settings, e.settings));
		}
		function o(e) {
			let t = -1;
			if (e != null) {
				if (Array.isArray(e)) for (; ++t < e.length;) {
					let n = e[t];
					i(n);
				}
				else throw TypeError("Expected a list of plugins, not `" + e + "`");
			}
		}
		function s(e, t) {
			let r = -1, i = -1;
			for (; ++r < n.length;) if (n[r][0] === e) {
				i = r;
				break;
			}
			if (i === -1) n.push([e, ...t]);
			else if (t.length > 0) {
				let [r, ...a] = t, o = n[i][1];
				_a(o) && _a(r) && (r = (0, ha.default)(!0, o, r)), n[i] = [
					e,
					r,
					...a
				];
			}
		}
	}
}().freeze();
function Ha(e, t) {
	if (typeof t != "function") throw TypeError("Cannot `" + e + "` without `parser`");
}
function Ua(e, t) {
	if (typeof t != "function") throw TypeError("Cannot `" + e + "` without `compiler`");
}
function Wa(e, t) {
	if (t) throw Error("Cannot call `" + e + "` on a frozen processor.\nCreate a new processor first, by calling it: use `processor()` instead of `processor`.");
}
function Ga(e) {
	if (!_a(e) || typeof e.type != "string") throw TypeError("Expected node, got `" + e + "`");
}
function Ka(e, t, n) {
	if (!n) throw Error("`" + e + "` finished async. Use `" + t + "` instead");
}
function qa(e) {
	return Ja(e) ? e : new Pa(e);
}
function Ja(e) {
	return !!(e && typeof e == "object" && "message" in e && "messages" in e);
}
function Ya(e) {
	return typeof e == "string" || Xa(e);
}
function Xa(e) {
	return !!(e && typeof e == "object" && "byteLength" in e && "byteOffset" in e);
}
// node_modules/.pnpm/orderedmap@2.1.1/node_modules/orderedmap/dist/index.js
function F(e) {
	this.content = e;
}
F.prototype = {
	constructor: F,
	find: function(e) {
		for (var t = 0; t < this.content.length; t += 2) if (this.content[t] === e) return t;
		return -1;
	},
	get: function(e) {
		var t = this.find(e);
		return t == -1 ? void 0 : this.content[t + 1];
	},
	update: function(e, t, n) {
		var r = n && n != e ? this.remove(n) : this, i = r.find(e), a = r.content.slice();
		return i == -1 ? a.push(n || e, t) : (a[i + 1] = t, n && (a[i] = n)), new F(a);
	},
	remove: function(e) {
		var t = this.find(e);
		if (t == -1) return this;
		var n = this.content.slice();
		return n.splice(t, 2), new F(n);
	},
	addToStart: function(e, t) {
		return new F([e, t].concat(this.remove(e).content));
	},
	addToEnd: function(e, t) {
		var n = this.remove(e).content.slice();
		return n.push(e, t), new F(n);
	},
	addBefore: function(e, t, n) {
		var r = this.remove(t), i = r.content.slice(), a = r.find(e);
		return i.splice(a == -1 ? i.length : a, 0, t, n), new F(i);
	},
	forEach: function(e) {
		for (var t = 0; t < this.content.length; t += 2) e(this.content[t], this.content[t + 1]);
	},
	prepend: function(e) {
		return e = F.from(e), e.size ? new F(e.content.concat(this.subtract(e).content)) : this;
	},
	append: function(e) {
		return e = F.from(e), e.size ? new F(this.subtract(e).content.concat(e.content)) : this;
	},
	subtract: function(e) {
		var t = this;
		e = F.from(e);
		for (var n = 0; n < e.content.length; n += 2) t = t.remove(e.content[n]);
		return t;
	},
	toObject: function() {
		var e = {};
		return this.forEach(function(t, n) {
			e[t] = n;
		}), e;
	},
	get size() {
		return this.content.length >> 1;
	}
}, F.from = function(e) {
	if (e instanceof F) return e;
	var t = [];
	if (e) for (var n in e) t.push(n, e[n]);
	return new F(t);
};
// node_modules/.pnpm/prosemirror-model@1.25.12/node_modules/prosemirror-model/dist/index.js
function Za(e, t, n) {
	for (let r = 0;; r++) {
		if (r == e.childCount || r == t.childCount) return e.childCount == t.childCount ? null : n;
		let i = e.child(r), a = t.child(r);
		if (i == a) n += i.nodeSize;
		else {
			if (!i.sameMarkup(a)) return n;
			if (i.isText && i.text != a.text) {
				let e = i.text, t = a.text, r = 0;
				for (; e[r] == t[r]; r++) n++;
				return r && r < e.length && r < t.length && eo(e.charCodeAt(r - 1)) && $a(e.charCodeAt(r)) && n--, n;
			}
			if (i.content.size || a.content.size) {
				let e = Za(i.content, a.content, n + 1);
				if (e != null) return e;
			}
			n += i.nodeSize;
		}
	}
}
function Qa(e, t, n, r) {
	for (let i = e.childCount, a = t.childCount;;) {
		if (i == 0 || a == 0) return i == a ? null : {
			a: n,
			b: r
		};
		let o = e.child(--i), s = t.child(--a), c = o.nodeSize;
		if (o == s) n -= c, r -= c;
		else {
			if (!o.sameMarkup(s)) return {
				a: n,
				b: r
			};
			if (o.isText && o.text != s.text) {
				let e = o.text, t = s.text, i = e.length, a = t.length;
				for (; i > 0 && a > 0 && e[i - 1] == t[a - 1];) i--, a--, n--, r--;
				return i && a && i < e.length && eo(e.charCodeAt(i - 1)) && $a(e.charCodeAt(i)) && (n++, r++), {
					a: n,
					b: r
				};
			}
			if (o.content.size || s.content.size) {
				let e = Qa(o.content, s.content, n - 1, r - 1);
				if (e) return e;
			}
			n -= c, r -= c;
		}
	}
}
function $a(e) {
	return e >= 56320 && e < 57344;
}
function eo(e) {
	return e >= 55296 && e < 56320;
}
var I = class e {
	constructor(e, t) {
		if (this.content = e, this.size = t || 0, t == null) for (let t = 0; t < e.length; t++) this.size += e[t].nodeSize;
	}
	nodesBetween(e, t, n, r = 0, i) {
		for (let a = 0, o = 0; o < t; a++) {
			let s = this.content[a], c = o + s.nodeSize;
			if (c > e && n(s, r + o, i || null, a) !== !1 && s.content.size) {
				let i = o + 1;
				s.nodesBetween(Math.max(0, e - i), Math.min(s.content.size, t - i), n, r + i);
			}
			o = c;
		}
	}
	descendants(e) {
		this.nodesBetween(0, this.size, e);
	}
	textBetween(e, t, n, r) {
		let i = "", a = !0;
		return this.nodesBetween(e, t, (o, s) => {
			let c = o.isText ? o.text.slice(Math.max(e, s) - s, t - s) : o.isLeaf ? r ? typeof r == "function" ? r(o) : r : o.type.spec.leafText ? o.type.spec.leafText(o) : "" : "";
			o.isBlock && (o.isLeaf && c || o.isTextblock) && n && (a ? a = !1 : i += n), i += c;
		}, 0), i;
	}
	append(t) {
		if (!t.size) return this;
		if (!this.size) return t;
		let n = this.lastChild, r = t.firstChild, i = this.content.slice(), a = 0;
		for (n.isText && n.sameMarkup(r) && (i[i.length - 1] = n.withText(n.text + r.text), a = 1); a < t.content.length; a++) i.push(t.content[a]);
		return new e(i, this.size + t.size);
	}
	cut(t, n = this.size) {
		if (t == 0 && n == this.size) return this;
		let r = [], i = 0;
		if (n > t) for (let e = 0, a = 0; a < n; e++) {
			let o = this.content[e], s = a + o.nodeSize;
			s > t && ((a < t || s > n) && (o = o.isText ? o.cut(Math.max(0, t - a), Math.min(o.text.length, n - a)) : o.cut(Math.max(0, t - a - 1), Math.min(o.content.size, n - a - 1))), r.push(o), i += o.nodeSize), a = s;
		}
		return new e(r, i);
	}
	cutByIndex(t, n) {
		return t == n ? e.empty : t == 0 && n == this.content.length ? this : new e(this.content.slice(t, n));
	}
	replaceChild(t, n) {
		let r = this.content[t];
		if (r == n) return this;
		let i = this.content.slice(), a = this.size + n.nodeSize - r.nodeSize;
		return i[t] = n, new e(i, a);
	}
	addToStart(t) {
		return new e([t].concat(this.content), this.size + t.nodeSize);
	}
	addToEnd(t) {
		return new e(this.content.concat(t), this.size + t.nodeSize);
	}
	eq(e) {
		if (this.content.length != e.content.length) return !1;
		for (let t = 0; t < this.content.length; t++) if (!this.content[t].eq(e.content[t])) return !1;
		return !0;
	}
	get firstChild() {
		return this.content.length ? this.content[0] : null;
	}
	get lastChild() {
		return this.content.length ? this.content[this.content.length - 1] : null;
	}
	get childCount() {
		return this.content.length;
	}
	child(e) {
		let t = this.content[e];
		if (!t) throw RangeError("Index " + e + " out of range for " + this);
		return t;
	}
	maybeChild(e) {
		return this.content[e] || null;
	}
	forEach(e) {
		for (let t = 0, n = 0; t < this.content.length; t++) {
			let r = this.content[t];
			e(r, n, t), n += r.nodeSize;
		}
	}
	findDiffStart(e, t = 0) {
		return Za(this, e, t);
	}
	findDiffEnd(e, t = this.size, n = e.size) {
		return Qa(this, e, t, n);
	}
	findIndex(e) {
		if (e == 0) return no(0, e);
		if (e == this.size) return no(this.content.length, e);
		if (e > this.size || e < 0) throw RangeError(`Position ${e} outside of fragment (${this})`);
		for (let t = 0, n = 0;; t++) {
			let r = this.child(t), i = n + r.nodeSize;
			if (i >= e) return i == e ? no(t + 1, i) : no(t, n);
			n = i;
		}
	}
	toString() {
		return "<" + this.toStringInner() + ">";
	}
	toStringInner() {
		return this.content.join(", ");
	}
	toJSON() {
		return this.content.length ? this.content.map((e) => e.toJSON()) : null;
	}
	static fromJSON(t, n) {
		if (!n) return e.empty;
		if (!Array.isArray(n)) throw RangeError("Invalid input for Fragment.fromJSON");
		return e.fromArray(n.map(t.nodeFromJSON));
	}
	static fromArray(t) {
		if (!t.length) return e.empty;
		let n, r = 0;
		for (let e = 0; e < t.length; e++) {
			let i = t[e];
			r += i.nodeSize, e && i.isText && t[e - 1].sameMarkup(i) ? (n ||= t.slice(0, e), n[n.length - 1] = i.withText(n[n.length - 1].text + i.text)) : n && n.push(i);
		}
		return new e(n || t, r);
	}
	static from(t) {
		if (!t) return e.empty;
		if (t instanceof e) return t;
		if (Array.isArray(t)) return this.fromArray(t);
		if (t.attrs) return new e([t], t.nodeSize);
		throw RangeError("Can not convert " + t + " to a Fragment" + (t.nodesBetween ? " (looks like multiple versions of prosemirror-model were loaded)" : ""));
	}
};
I.empty = new I([], 0);
var to = {
	index: 0,
	offset: 0
};
function no(e, t) {
	return to.index = e, to.offset = t, to;
}
function ro(e, t) {
	if (e === t) return !0;
	if (!(e && typeof e == "object") || !(t && typeof t == "object")) return !1;
	let n = Array.isArray(e);
	if (Array.isArray(t) != n) return !1;
	if (n) {
		if (e.length != t.length) return !1;
		for (let n = 0; n < e.length; n++) if (!ro(e[n], t[n])) return !1;
	} else {
		for (let n in e) if (!(n in t) || !ro(e[n], t[n])) return !1;
		for (let n in t) if (!(n in e)) return !1;
	}
	return !0;
}
var L = class e {
	constructor(e, t) {
		this.type = e, this.attrs = t;
	}
	addToSet(e) {
		let t, n = !1;
		for (let r = 0; r < e.length; r++) {
			let i = e[r];
			if (this.eq(i)) return e;
			if (this.type.excludes(i.type)) t ||= e.slice(0, r);
			else if (i.type.excludes(this.type)) return e;
			else !n && i.type.rank > this.type.rank && (t ||= e.slice(0, r), t.push(this), n = !0), t && t.push(i);
		}
		return t ||= e.slice(), n || t.push(this), t;
	}
	removeFromSet(e) {
		for (let t = 0; t < e.length; t++) if (this.eq(e[t])) return e.slice(0, t).concat(e.slice(t + 1));
		return e;
	}
	isInSet(e) {
		for (let t = 0; t < e.length; t++) if (this.eq(e[t])) return !0;
		return !1;
	}
	eq(e) {
		return this == e || this.type == e.type && ro(this.attrs, e.attrs);
	}
	toJSON() {
		let e = { type: this.type.name };
		for (let t in this.attrs) {
			e.attrs = this.attrs;
			break;
		}
		return e;
	}
	static fromJSON(e, t) {
		if (!t) throw RangeError("Invalid input for Mark.fromJSON");
		let n = e.marks[t.type];
		if (!n) throw RangeError(`There is no mark type ${t.type} in this schema`);
		return n.create(t.attrs);
	}
	static sameSet(e, t) {
		if (e == t) return !0;
		if (e.length != t.length) return !1;
		for (let n = 0; n < e.length; n++) if (!e[n].eq(t[n])) return !1;
		return !0;
	}
	static setFrom(t) {
		if (!t || Array.isArray(t) && t.length == 0) return e.none;
		if (t instanceof e) return [t];
		let n = t.slice();
		return n.sort((e, t) => e.type.rank - t.type.rank), n;
	}
};
L.none = [];
var io = class extends Error {}, R = class e {
	constructor(e, t, n) {
		this.content = e, this.openStart = t, this.openEnd = n;
	}
	get size() {
		return this.content.size - this.openStart - this.openEnd;
	}
	insertAt(t, n) {
		let r = oo(this.content, t + this.openStart, n, this.openStart + 1, this.openEnd + 1);
		return r && new e(r, this.openStart, this.openEnd);
	}
	removeBetween(t, n) {
		return new e(ao(this.content, t + this.openStart, n + this.openStart), this.openStart, this.openEnd);
	}
	eq(e) {
		return this.content.eq(e.content) && this.openStart == e.openStart && this.openEnd == e.openEnd;
	}
	toString() {
		return this.content + "(" + this.openStart + "," + this.openEnd + ")";
	}
	toJSON() {
		if (!this.content.size) return null;
		let e = { content: this.content.toJSON() };
		return this.openStart > 0 && (e.openStart = this.openStart), this.openEnd > 0 && (e.openEnd = this.openEnd), e;
	}
	static fromJSON(t, n) {
		if (!n) return e.empty;
		let r = n.openStart || 0, i = n.openEnd || 0;
		if (typeof r != "number" || typeof i != "number") throw RangeError("Invalid input for Slice.fromJSON");
		return new e(I.fromJSON(t, n.content), r, i);
	}
	static maxOpen(t, n = !0) {
		let r = 0, i = 0;
		for (let e = t.firstChild; e && !e.isLeaf && (n || !e.type.spec.isolating); e = e.firstChild) r++;
		for (let e = t.lastChild; e && !e.isLeaf && (n || !e.type.spec.isolating); e = e.lastChild) i++;
		return new e(t, r, i);
	}
};
R.empty = new R(I.empty, 0, 0);
function ao(e, t, n) {
	let { index: r, offset: i } = e.findIndex(t), a = e.maybeChild(r), { index: o, offset: s } = e.findIndex(n);
	if (i == t || a.isText) {
		if (s != n && !e.child(o).isText) throw RangeError("Removing non-flat range");
		return e.cut(0, t).append(e.cut(n));
	}
	if (r != o) throw RangeError("Removing non-flat range");
	return e.replaceChild(r, a.copy(ao(a.content, t - i - 1, n - i - 1)));
}
function oo(e, t, n, r, i, a) {
	let { index: o, offset: s } = e.findIndex(t), c = e.maybeChild(o);
	if (s == t || c.isText) return a && r <= 0 && i <= 0 && !a.canReplace(o, o, n) ? null : e.cut(0, t).append(n).append(e.cut(t));
	let l = oo(c.content, t - s - 1, n, o == 0 ? r - 1 : 0, o == e.childCount - 1 ? i - 1 : 0, c);
	return l && e.replaceChild(o, c.copy(l));
}
function so(e, t, n) {
	if (n.openStart > e.depth) throw new io("Inserted content deeper than insertion position");
	if (e.depth - n.openStart != t.depth - n.openEnd) throw new io("Inconsistent open depths");
	return co(e, t, n, 0);
}
function co(e, t, n, r) {
	let i = e.index(r), a = e.node(r);
	if (i == t.index(r) && r < e.depth - n.openStart) {
		let o = co(e, t, n, r + 1);
		return a.copy(a.content.replaceChild(i, o));
	}
	if (!n.content.size) return mo(a, go(e, t, r));
	if (!n.openStart && !n.openEnd && e.depth == r && t.depth == r) {
		let r = e.parent, i = r.content;
		return mo(r, i.cut(0, e.parentOffset).append(n.content).append(i.cut(t.parentOffset)));
	}
	{
		let { start: i, end: o } = _o(n, e);
		return mo(a, ho(e, i, o, t, r));
	}
}
function lo(e, t) {
	if (!t.type.compatibleContent(e.type)) throw new io("Cannot join " + t.type.name + " onto " + e.type.name);
}
function uo(e, t, n) {
	let r = e.node(n);
	return lo(r, t.node(n)), r;
}
function fo(e, t) {
	let n = t.length - 1;
	n >= 0 && e.isText && e.sameMarkup(t[n]) ? t[n] = e.withText(t[n].text + e.text) : t.push(e);
}
function po(e, t, n, r) {
	let i = (t || e).node(n), a = 0, o = t ? t.index(n) : i.childCount;
	e && (a = e.index(n), e.depth > n ? a++ : e.textOffset && (fo(e.nodeAfter, r), a++));
	for (let e = a; e < o; e++) fo(i.child(e), r);
	t && t.depth == n && t.textOffset && fo(t.nodeBefore, r);
}
function mo(e, t) {
	if (!e.type.validContent(t)) throw new io("Invalid content for node " + e.type.name);
	return e.copy(t);
}
function ho(e, t, n, r, i) {
	let a = e.depth > i && uo(e, t, i + 1), o = r.depth > i && uo(n, r, i + 1), s = [];
	return po(null, e, i, s), a && o && t.index(i) == n.index(i) ? (lo(a, o), fo(mo(a, ho(e, t, n, r, i + 1)), s)) : (a && fo(mo(a, go(e, t, i + 1)), s), po(t, n, i, s), o && fo(mo(o, go(n, r, i + 1)), s)), po(r, null, i, s), new I(s);
}
function go(e, t, n) {
	let r = [];
	return po(null, e, n, r), e.depth > n && fo(mo(uo(e, t, n + 1), go(e, t, n + 1)), r), po(t, null, n, r), new I(r);
}
function _o(e, t) {
	let n = t.depth - e.openStart, r = t.node(n).copy(e.content);
	for (let e = n - 1; e >= 0; e--) r = t.node(e).copy(I.from(r));
	return {
		start: r.resolveNoCache(e.openStart + n),
		end: r.resolveNoCache(r.content.size - e.openEnd - n)
	};
}
var vo = class e {
	constructor(e, t, n) {
		this.pos = e, this.path = t, this.parentOffset = n, this.depth = t.length / 3 - 1;
	}
	resolveDepth(e) {
		return e == null ? this.depth : e < 0 ? this.depth + e : e;
	}
	get parent() {
		return this.node(this.depth);
	}
	get doc() {
		return this.node(0);
	}
	node(e) {
		return this.path[this.resolveDepth(e) * 3];
	}
	index(e) {
		return this.path[this.resolveDepth(e) * 3 + 1];
	}
	indexAfter(e) {
		return e = this.resolveDepth(e), this.index(e) + (e == this.depth && !this.textOffset ? 0 : 1);
	}
	start(e) {
		return e = this.resolveDepth(e), e == 0 ? 0 : this.path[e * 3 - 1] + 1;
	}
	end(e) {
		return e = this.resolveDepth(e), this.start(e) + this.node(e).content.size;
	}
	before(e) {
		if (e = this.resolveDepth(e), !e) throw RangeError("There is no position before the top-level node");
		return e == this.depth + 1 ? this.pos : this.path[e * 3 - 1];
	}
	after(e) {
		if (e = this.resolveDepth(e), !e) throw RangeError("There is no position after the top-level node");
		return e == this.depth + 1 ? this.pos : this.path[e * 3 - 1] + this.path[e * 3].nodeSize;
	}
	get textOffset() {
		return this.pos - this.path[this.path.length - 1];
	}
	get nodeAfter() {
		let e = this.parent, t = this.index(this.depth);
		if (t == e.childCount) return null;
		let n = this.pos - this.path[this.path.length - 1], r = e.child(t);
		return n ? e.child(t).cut(n) : r;
	}
	get nodeBefore() {
		let e = this.index(this.depth), t = this.pos - this.path[this.path.length - 1];
		return t ? this.parent.child(e).cut(0, t) : e == 0 ? null : this.parent.child(e - 1);
	}
	posAtIndex(e, t) {
		t = this.resolveDepth(t);
		let n = this.path[t * 3], r = t == 0 ? 0 : this.path[t * 3 - 1] + 1;
		for (let t = 0; t < e; t++) r += n.child(t).nodeSize;
		return r;
	}
	marks() {
		let e = this.parent, t = this.index();
		if (e.content.size == 0) return L.none;
		if (this.textOffset) return e.child(t).marks;
		let n = e.maybeChild(t - 1), r = e.maybeChild(t);
		if (!n) {
			let e = n;
			n = r, r = e;
		}
		let i = n.marks;
		for (var a = 0; a < i.length; a++) i[a].type.spec.inclusive !== !1 || r && i[a].isInSet(r.marks) || (i = i[a--].removeFromSet(i));
		return i;
	}
	marksAcross(e) {
		let t = this.parent.maybeChild(this.index());
		if (!t || !t.isInline) return null;
		let n = t.marks, r = e.parent.maybeChild(e.index());
		for (var i = 0; i < n.length; i++) n[i].type.spec.inclusive !== !1 || r && n[i].isInSet(r.marks) || (n = n[i--].removeFromSet(n));
		return n;
	}
	sharedDepth(e) {
		for (let t = this.depth; t > 0; t--) if (this.start(t) <= e && this.end(t) >= e) return t;
		return 0;
	}
	blockRange(e = this, t) {
		if (e.pos < this.pos) return e.blockRange(this);
		for (let n = this.depth - (this.parent.inlineContent || this.pos == e.pos ? 1 : 0); n >= 0; n--) if (e.pos <= this.end(n) && (!t || t(this.node(n)))) return new So(this, e, n);
		return null;
	}
	sameParent(e) {
		return this.pos - this.parentOffset == e.pos - e.parentOffset;
	}
	max(e) {
		return e.pos > this.pos ? e : this;
	}
	min(e) {
		return e.pos < this.pos ? e : this;
	}
	toString() {
		let e = "";
		for (let t = 1; t <= this.depth; t++) e += (e ? "/" : "") + this.node(t).type.name + "_" + this.index(t - 1);
		return e + ":" + this.parentOffset;
	}
	static resolve(t, n) {
		if (!(n >= 0 && n <= t.content.size)) throw RangeError("Position " + n + " out of range");
		let r = [], i = 0, a = n;
		for (let e = t;;) {
			let { index: t, offset: n } = e.content.findIndex(a), o = a - n;
			if (r.push(e, t, i + n), !o || (e = e.child(t), e.isText)) break;
			a = o - 1, i += n + 1;
		}
		return new e(n, r, a);
	}
	static resolveCached(t, n) {
		let r = xo.get(t);
		if (r) for (let e = 0; e < r.elts.length; e++) {
			let t = r.elts[e];
			if (t.pos == n) return t;
		}
		else xo.set(t, r = new yo());
		let i = r.elts[r.i] = e.resolve(t, n);
		return r.i = (r.i + 1) % bo, i;
	}
}, yo = class {
	constructor() {
		this.elts = [], this.i = 0;
	}
}, bo = 12, xo = /* @__PURE__ */ new WeakMap(), So = class {
	constructor(e, t, n) {
		this.$from = e, this.$to = t, this.depth = n;
	}
	get start() {
		return this.$from.before(this.depth + 1);
	}
	get end() {
		return this.$to.after(this.depth + 1);
	}
	get parent() {
		return this.$from.node(this.depth);
	}
	get startIndex() {
		return this.$from.index(this.depth);
	}
	get endIndex() {
		return this.$to.indexAfter(this.depth);
	}
}, Co = Object.create(null), wo = class e {
	constructor(e, t, n, r = L.none) {
		this.type = e, this.attrs = t, this.marks = r, this.content = n || I.empty;
	}
	get children() {
		return this.content.content;
	}
	get nodeSize() {
		return this.isLeaf ? 1 : 2 + this.content.size;
	}
	get childCount() {
		return this.content.childCount;
	}
	child(e) {
		return this.content.child(e);
	}
	maybeChild(e) {
		return this.content.maybeChild(e);
	}
	forEach(e) {
		this.content.forEach(e);
	}
	nodesBetween(e, t, n, r = 0) {
		this.content.nodesBetween(e, t, n, r, this);
	}
	descendants(e) {
		this.nodesBetween(0, this.content.size, e);
	}
	get textContent() {
		return this.isLeaf && this.type.spec.leafText ? this.type.spec.leafText(this) : this.textBetween(0, this.content.size, "");
	}
	textBetween(e, t, n, r) {
		return this.content.textBetween(e, t, n, r);
	}
	get firstChild() {
		return this.content.firstChild;
	}
	get lastChild() {
		return this.content.lastChild;
	}
	eq(e) {
		return this == e || this.sameMarkup(e) && this.content.eq(e.content);
	}
	sameMarkup(e) {
		return this.hasMarkup(e.type, e.attrs, e.marks);
	}
	hasMarkup(e, t, n) {
		return this.type == e && ro(this.attrs, t || e.defaultAttrs || Co) && L.sameSet(this.marks, n || L.none);
	}
	copy(t = null) {
		return t == this.content ? this : new e(this.type, this.attrs, t, this.marks);
	}
	mark(t) {
		return t == this.marks ? this : new e(this.type, this.attrs, this.content, t);
	}
	cut(e, t = this.content.size) {
		return e == 0 && t == this.content.size ? this : this.copy(this.content.cut(e, t));
	}
	slice(e, t = this.content.size, n = !1) {
		if (e == t) return R.empty;
		let r = this.resolve(e), i = this.resolve(t), a = n ? 0 : r.sharedDepth(t), o = r.start(a);
		return new R(r.node(a).content.cut(r.pos - o, i.pos - o), r.depth - a, i.depth - a);
	}
	replace(e, t, n) {
		return so(this.resolve(e), this.resolve(t), n);
	}
	nodeAt(e) {
		for (let t = this;;) {
			let { index: n, offset: r } = t.content.findIndex(e);
			if (t = t.maybeChild(n), !t) return null;
			if (r == e || t.isText) return t;
			e -= r + 1;
		}
	}
	childAfter(e) {
		let { index: t, offset: n } = this.content.findIndex(e);
		return {
			node: this.content.maybeChild(t),
			index: t,
			offset: n
		};
	}
	childBefore(e) {
		if (e == 0) return {
			node: null,
			index: 0,
			offset: 0
		};
		let { index: t, offset: n } = this.content.findIndex(e);
		if (n < e) return {
			node: this.content.child(t),
			index: t,
			offset: n
		};
		let r = this.content.child(t - 1);
		return {
			node: r,
			index: t - 1,
			offset: n - r.nodeSize
		};
	}
	resolve(e) {
		return vo.resolveCached(this, e);
	}
	resolveNoCache(e) {
		return vo.resolve(this, e);
	}
	rangeHasMark(e, t, n) {
		let r = !1;
		return t > e && this.nodesBetween(e, t, (e) => (n.isInSet(e.marks) && (r = !0), !r)), r;
	}
	get isBlock() {
		return this.type.isBlock;
	}
	get isTextblock() {
		return this.type.isTextblock;
	}
	get inlineContent() {
		return this.type.inlineContent;
	}
	get isInline() {
		return this.type.isInline;
	}
	get isText() {
		return this.type.isText;
	}
	get isLeaf() {
		return this.type.isLeaf;
	}
	get isAtom() {
		return this.type.isAtom;
	}
	toString() {
		if (this.type.spec.toDebugString) return this.type.spec.toDebugString(this);
		let e = this.type.name;
		return this.content.size && (e += "(" + this.content.toStringInner() + ")"), Eo(this.marks, e);
	}
	contentMatchAt(e) {
		let t = this.type.contentMatch.matchFragment(this.content, 0, e);
		if (!t) throw Error("Called contentMatchAt on a node with invalid content");
		return t;
	}
	canReplace(e, t, n = I.empty, r = 0, i = n.childCount) {
		let a = this.contentMatchAt(e).matchFragment(n, r, i), o = a && a.matchFragment(this.content, t);
		if (!o || !o.validEnd) return !1;
		for (let e = r; e < i; e++) if (!this.type.allowsMarks(n.child(e).marks)) return !1;
		return !0;
	}
	canReplaceWith(e, t, n, r) {
		if (r && !this.type.allowsMarks(r)) return !1;
		let i = this.contentMatchAt(e).matchType(n), a = i && i.matchFragment(this.content, t);
		return a ? a.validEnd : !1;
	}
	canAppend(e) {
		return e.content.size ? this.canReplace(this.childCount, this.childCount, e.content) : this.type.compatibleContent(e.type);
	}
	check() {
		this.type.checkContent(this.content), Do(this.type.attrs, this.attrs, "node", this.type.name);
		let e = L.none;
		for (let t = 0; t < this.marks.length; t++) {
			let n = this.marks[t];
			Do(n.type.attrs, n.attrs, "mark", n.type.name), e = n.addToSet(e);
		}
		if (!L.sameSet(e, this.marks)) throw RangeError(`Invalid collection of marks for node ${this.type.name}: ${this.marks.map((e) => e.type.name)}`);
		this.content.forEach((e) => e.check());
	}
	toJSON() {
		let e = { type: this.type.name };
		for (let t in this.attrs) {
			e.attrs = this.attrs;
			break;
		}
		return this.content.size && (e.content = this.content.toJSON()), this.marks.length && (e.marks = this.marks.map((e) => e.toJSON())), e;
	}
	static fromJSON(e, t) {
		if (!t) throw RangeError("Invalid input for Node.fromJSON");
		let n;
		if (t.marks) {
			if (!Array.isArray(t.marks)) throw RangeError("Invalid mark data for Node.fromJSON");
			n = t.marks.map(e.markFromJSON);
		}
		if (t.type == "text") {
			if (typeof t.text != "string") throw RangeError("Invalid text node in JSON");
			return e.text(t.text, n);
		}
		let r = I.fromJSON(e, t.content);
		return e.nodeType(t.type).create(t.attrs, r, n);
	}
};
wo.prototype.text = void 0;
var To = class e extends wo {
	constructor(e, t, n, r) {
		if (super(e, t, null, r), !n) throw RangeError("Empty text nodes are not allowed");
		this.text = n;
	}
	toString() {
		return this.type.spec.toDebugString ? this.type.spec.toDebugString(this) : Eo(this.marks, JSON.stringify(this.text));
	}
	get textContent() {
		return this.text;
	}
	textBetween(e, t) {
		return this.text.slice(e, t);
	}
	get nodeSize() {
		return this.text.length;
	}
	mark(t) {
		return t == this.marks ? this : new e(this.type, this.attrs, this.text, t);
	}
	withText(t) {
		return t == this.text ? this : new e(this.type, this.attrs, t, this.marks);
	}
	cut(e = 0, t = this.text.length) {
		return e == 0 && t == this.text.length ? this : this.withText(this.text.slice(e, t));
	}
	eq(e) {
		return this.sameMarkup(e) && this.text == e.text;
	}
	toJSON() {
		let e = super.toJSON();
		return e.text = this.text, e;
	}
};
function Eo(e, t) {
	for (let n = e.length - 1; n >= 0; n--) t = e[n].type.name + "(" + t + ")";
	return t;
}
function Do(e, t, n, r) {
	for (let i in t) if (!(i in e)) throw RangeError(`Unsupported attribute ${i} for ${n} of type ${r}`);
	for (let n in e) e[n].validate && e[n].validate(t[n]);
}
var Oo = class e {
	constructor(e) {
		this.validEnd = e, this.next = [], this.wrapCache = [];
	}
	static parse(t, n) {
		let r = new ko(t, n);
		if (r.next == null) return e.empty;
		let i = Ao(r);
		r.next && r.err("Unexpected trailing text");
		let a = Bo(Lo(i));
		return Vo(a, r), a;
	}
	matchType(e) {
		for (let t = 0; t < this.next.length; t++) if (this.next[t].type == e) return this.next[t].next;
		return null;
	}
	matchFragment(e, t = 0, n = e.childCount) {
		let r = this;
		for (let i = t; r && i < n; i++) r = r.matchType(e.child(i).type);
		return r;
	}
	get inlineContent() {
		return this.next.length != 0 && this.next[0].type.isInline;
	}
	get defaultType() {
		for (let e = 0; e < this.next.length; e++) {
			let { type: t } = this.next[e];
			if (!(t.isText || t.hasRequiredAttrs())) return t;
		}
		return null;
	}
	compatible(e) {
		for (let t = 0; t < this.next.length; t++) for (let n = 0; n < e.next.length; n++) if (this.next[t].type == e.next[n].type) return !0;
		return !1;
	}
	fillBefore(e, t = !1, n = 0) {
		let r = [this];
		function i(a, o) {
			let s = a.matchFragment(e, n);
			if (s && (!t || s.validEnd)) return I.from(o.map((e) => e.createAndFill()));
			for (let e = 0; e < a.next.length; e++) {
				let { type: t, next: n } = a.next[e];
				if (!(t.isText || t.hasRequiredAttrs()) && r.indexOf(n) == -1) {
					r.push(n);
					let e = i(n, o.concat(t));
					if (e) return e;
				}
			}
			return null;
		}
		return i(this, []);
	}
	findWrapping(e) {
		for (let t = 0; t < this.wrapCache.length; t += 2) if (this.wrapCache[t] == e) return this.wrapCache[t + 1];
		let t = this.computeWrapping(e);
		return this.wrapCache.push(e, t), t;
	}
	computeWrapping(e) {
		let t = Object.create(null), n = [{
			match: this,
			type: null,
			via: null
		}];
		for (; n.length;) {
			let r = n.shift(), i = r.match;
			if (i.matchType(e)) {
				let e = [];
				for (let t = r; t.type; t = t.via) e.push(t.type);
				return e.reverse();
			}
			for (let e = 0; e < i.next.length; e++) {
				let { type: a, next: o } = i.next[e];
				a.isLeaf || a.hasRequiredAttrs() || a.name in t || r.type && !o.validEnd || (n.push({
					match: a.contentMatch,
					type: a,
					via: r
				}), t[a.name] = !0);
			}
		}
		return null;
	}
	get edgeCount() {
		return this.next.length;
	}
	edge(e) {
		if (e >= this.next.length) throw RangeError(`There's no ${e}th edge in this content match`);
		return this.next[e];
	}
	toString() {
		let e = [];
		function t(n) {
			e.push(n);
			for (let r = 0; r < n.next.length; r++) e.indexOf(n.next[r].next) == -1 && t(n.next[r].next);
		}
		return t(this), e.map((t, n) => {
			let r = n + (t.validEnd ? "*" : " ") + " ";
			for (let n = 0; n < t.next.length; n++) r += (n ? ", " : "") + t.next[n].type.name + "->" + e.indexOf(t.next[n].next);
			return r;
		}).join("\n");
	}
};
Oo.empty = new Oo(!0);
var ko = class {
	constructor(e, t) {
		this.string = e, this.nodeTypes = t, this.inline = null, this.pos = 0, this.tokens = e.split(/\s*(?=\b|\W|$)/), this.tokens[this.tokens.length - 1] == "" && this.tokens.pop(), this.tokens[0] == "" && this.tokens.shift();
	}
	get next() {
		return this.tokens[this.pos];
	}
	eat(e) {
		return this.next == e && (this.pos++ || !0);
	}
	err(e) {
		throw SyntaxError(e + " (in content expression '" + this.string + "')");
	}
};
function Ao(e) {
	let t = [];
	do
		t.push(jo(e));
	while (e.eat("|"));
	return t.length == 1 ? t[0] : {
		type: "choice",
		exprs: t
	};
}
function jo(e) {
	let t = [];
	do
		t.push(Mo(e));
	while (e.next && e.next != ")" && e.next != "|");
	return t.length == 1 ? t[0] : {
		type: "seq",
		exprs: t
	};
}
function Mo(e) {
	let t = Io(e);
	for (;;) if (e.eat("+")) t = {
		type: "plus",
		expr: t
	};
	else if (e.eat("*")) t = {
		type: "star",
		expr: t
	};
	else if (e.eat("?")) t = {
		type: "opt",
		expr: t
	};
	else if (e.eat("{")) t = Po(e, t);
	else break;
	return t;
}
function No(e) {
	/\D/.test(e.next) && e.err("Expected number, got '" + e.next + "'");
	let t = Number(e.next);
	return e.pos++, t;
}
function Po(e, t) {
	let n = No(e), r = n;
	return e.eat(",") && (r = e.next == "}" ? -1 : No(e)), e.eat("}") || e.err("Unclosed braced range"), {
		type: "range",
		min: n,
		max: r,
		expr: t
	};
}
function Fo(e, t) {
	let n = e.nodeTypes, r = n[t];
	if (r) return [r];
	let i = [];
	for (let e in n) {
		let r = n[e];
		r.isInGroup(t) && i.push(r);
	}
	return i.length == 0 && e.err("No node type or group '" + t + "' found"), i;
}
function Io(e) {
	if (e.eat("(")) {
		let t = Ao(e);
		return e.eat(")") || e.err("Missing closing paren"), t;
	}
	if (/\W/.test(e.next)) e.err("Unexpected token '" + e.next + "'");
	else {
		let t = Fo(e, e.next).map((t) => (e.inline == null ? e.inline = t.isInline : e.inline != t.isInline && e.err("Mixing inline and block content"), {
			type: "name",
			value: t
		}));
		return e.pos++, t.length == 1 ? t[0] : {
			type: "choice",
			exprs: t
		};
	}
}
function Lo(e) {
	let t = [[]];
	return i(a(e, 0), n()), t;
	function n() {
		return t.push([]) - 1;
	}
	function r(e, n, r) {
		let i = {
			term: r,
			to: n
		};
		return t[e].push(i), i;
	}
	function i(e, t) {
		e.forEach((e) => e.to = t);
	}
	function a(e, t) {
		if (e.type == "choice") return e.exprs.reduce((e, n) => e.concat(a(n, t)), []);
		if (e.type == "seq") for (let r = 0;; r++) {
			let o = a(e.exprs[r], t);
			if (r == e.exprs.length - 1) return o;
			i(o, t = n());
		}
		else if (e.type == "star") {
			let o = n();
			return r(t, o), i(a(e.expr, o), o), [r(o)];
		} else if (e.type == "plus") {
			let o = n();
			return i(a(e.expr, t), o), i(a(e.expr, o), o), [r(o)];
		} else if (e.type == "opt") return [r(t)].concat(a(e.expr, t));
		else if (e.type == "range") {
			let o = t;
			for (let t = 0; t < e.min; t++) {
				let t = n();
				i(a(e.expr, o), t), o = t;
			}
			if (e.max == -1) i(a(e.expr, o), o);
			else for (let t = e.min; t < e.max; t++) {
				let t = n();
				r(o, t), i(a(e.expr, o), t), o = t;
			}
			return [r(o)];
		} else if (e.type == "name") return [r(t, void 0, e.value)];
		else throw Error("Unknown expr type");
	}
}
function Ro(e, t) {
	return t - e;
}
function zo(e, t) {
	let n = [];
	return r(t), n.sort(Ro);
	function r(t) {
		let i = e[t];
		if (i.length == 1 && !i[0].term) return r(i[0].to);
		n.push(t);
		for (let e = 0; e < i.length; e++) {
			let { term: t, to: a } = i[e];
			!t && n.indexOf(a) == -1 && r(a);
		}
	}
}
function Bo(e) {
	let t = Object.create(null);
	return n(zo(e, 0));
	function n(r) {
		let i = [];
		r.forEach((t) => {
			e[t].forEach(({ term: t, to: n }) => {
				if (!t) return;
				let r;
				for (let e = 0; e < i.length; e++) i[e][0] == t && (r = i[e][1]);
				zo(e, n).forEach((e) => {
					r || i.push([t, r = []]), r.indexOf(e) == -1 && r.push(e);
				});
			});
		});
		let a = t[r.join(",")] = new Oo(r.indexOf(e.length - 1) > -1);
		for (let e = 0; e < i.length; e++) {
			let r = i[e][1].sort(Ro);
			a.next.push({
				type: i[e][0],
				next: t[r.join(",")] || n(r)
			});
		}
		return a;
	}
}
function Vo(e, t) {
	for (let n = 0, r = [e]; n < r.length; n++) {
		let e = r[n], i = !e.validEnd, a = [];
		for (let t = 0; t < e.next.length; t++) {
			let { type: n, next: o } = e.next[t];
			a.push(n.name), i && !(n.isText || n.hasRequiredAttrs()) && (i = !1), r.indexOf(o) == -1 && r.push(o);
		}
		i && t.err("Only non-generatable nodes (" + a.join(", ") + ") in a required position (see https://prosemirror.net/docs/guide/#generatable)");
	}
}
function Ho(e) {
	let t = Object.create(null);
	for (let n in e) {
		let r = e[n];
		if (!r.hasDefault) return null;
		t[n] = r.default;
	}
	return t;
}
function Uo(e, t) {
	let n = Object.create(null);
	for (let r in e) {
		let i = e[r], a = t && t[r];
		if (a === void 0) {
			if (i.hasDefault) a = i.default;
			else throw RangeError("No value supplied for attribute " + r);
		} else i.validate && i.validate(a);
		n[r] = a;
	}
	return n;
}
function Wo(e, t) {
	let n = Object.create(null);
	if (t) for (let r in t) n[r] = new qo(e, r, t[r]);
	return n;
}
var Go = class e {
	constructor(e, t, n) {
		this.name = e, this.schema = t, this.spec = n, this.markSet = null, this.groups = n.group ? n.group.split(" ") : [], this.attrs = Wo(e, n.attrs), this.defaultAttrs = Ho(this.attrs), this.contentMatch = null, this.inlineContent = null, this.isBlock = !(n.inline || e == "text"), this.isText = e == "text";
	}
	get isInline() {
		return !this.isBlock;
	}
	get isTextblock() {
		return this.isBlock && this.inlineContent;
	}
	get isLeaf() {
		return this.contentMatch == Oo.empty;
	}
	get isAtom() {
		return this.isLeaf || !!this.spec.atom;
	}
	isInGroup(e) {
		return this.groups.indexOf(e) > -1;
	}
	get whitespace() {
		return this.spec.whitespace || (this.spec.code ? "pre" : "normal");
	}
	hasRequiredAttrs() {
		for (let e in this.attrs) if (this.attrs[e].isRequired) return !0;
		return !1;
	}
	compatibleContent(e) {
		return this == e || this.contentMatch.compatible(e.contentMatch);
	}
	computeAttrs(e) {
		return !e && this.defaultAttrs ? this.defaultAttrs : Uo(this.attrs, e);
	}
	create(e = null, t, n) {
		if (this.isText) throw Error("NodeType.create can't construct text nodes");
		return new wo(this, this.computeAttrs(e), I.from(t), L.setFrom(n));
	}
	createChecked(e = null, t, n) {
		return t = I.from(t), this.checkContent(t), new wo(this, this.computeAttrs(e), t, L.setFrom(n));
	}
	createAndFill(e = null, t, n) {
		if (e = this.computeAttrs(e), t = I.from(t), t.size) {
			let e = this.contentMatch.fillBefore(t);
			if (!e) return null;
			t = e.append(t);
		}
		let r = this.contentMatch.matchFragment(t), i = r && r.fillBefore(I.empty, !0);
		return i ? new wo(this, e, t.append(i), L.setFrom(n)) : null;
	}
	validContent(e) {
		let t = this.contentMatch.matchFragment(e);
		if (!t || !t.validEnd) return !1;
		for (let t = 0; t < e.childCount; t++) if (!this.allowsMarks(e.child(t).marks)) return !1;
		return !0;
	}
	checkContent(e) {
		if (!this.validContent(e)) throw RangeError(`Invalid content for node ${this.name}: ${e.toString().slice(0, 50)}`);
	}
	checkAttrs(e) {}
	allowsMarkType(e) {
		return this.markSet == null || this.markSet.indexOf(e) > -1;
	}
	allowsMarks(e) {
		if (this.markSet == null) return !0;
		for (let t = 0; t < e.length; t++) if (!this.allowsMarkType(e[t].type)) return !1;
		return !0;
	}
	allowedMarks(e) {
		if (this.markSet == null) return e;
		let t;
		for (let n = 0; n < e.length; n++) this.allowsMarkType(e[n].type) ? t && t.push(e[n]) : t ||= e.slice(0, n);
		return t ? t.length ? t : L.none : e;
	}
	static compile(t, n) {
		let r = Object.create(null);
		t.forEach((t, i) => r[t] = new e(t, n, i));
		let i = n.spec.topNode || "doc";
		if (!r[i]) throw RangeError("Schema is missing its top node type ('" + i + "')");
		if (!r.text) throw RangeError("Every schema needs a 'text' type");
		for (let e in r.text.attrs) throw RangeError("The text node type should not have attributes");
		return r;
	}
};
function Ko(e, t, n) {
	let r = n.split("|");
	return (n) => {
		let i = n === null ? "null" : typeof n;
		if (r.indexOf(i) < 0) throw RangeError(`Expected value of type ${r} for attribute ${t} on type ${e}, got ${i}`);
	};
}
var qo = class {
	constructor(e, t, n) {
		this.hasDefault = Object.prototype.hasOwnProperty.call(n, "default"), this.default = n.default, this.validate = typeof n.validate == "string" ? Ko(e, t, n.validate) : n.validate;
	}
	get isRequired() {
		return !this.hasDefault;
	}
}, Jo = class e {
	constructor(e, t, n, r) {
		this.name = e, this.rank = t, this.schema = n, this.spec = r, this.attrs = Wo(e, r.attrs), this.excluded = null;
		let i = Ho(this.attrs);
		this.instance = i ? new L(this, i) : null;
	}
	create(e = null) {
		return !e && this.instance ? this.instance : new L(this, Uo(this.attrs, e));
	}
	static compile(t, n) {
		let r = Object.create(null), i = 0;
		return t.forEach((t, a) => r[t] = new e(t, i++, n, a)), r;
	}
	removeFromSet(e) {
		for (var t = 0; t < e.length; t++) e[t].type == this && (e = e.slice(0, t).concat(e.slice(t + 1)), t--);
		return e;
	}
	isInSet(e) {
		for (let t = 0; t < e.length; t++) if (e[t].type == this) return e[t];
	}
	excludes(e) {
		return this.excluded.indexOf(e) > -1;
	}
}, Yo = class {
	constructor(e) {
		this.linebreakReplacement = null, this.cached = Object.create(null);
		let t = this.spec = {};
		for (let n in e) t[n] = e[n];
		t.nodes = F.from(e.nodes), t.marks = F.from(e.marks || {}), this.nodes = Go.compile(this.spec.nodes, this), this.marks = Jo.compile(this.spec.marks, this);
		let n = Object.create(null);
		for (let e in this.nodes) {
			if (e in this.marks) throw RangeError(e + " can not be both a node and a mark");
			let t = this.nodes[e], r = t.spec.content || "", i = t.spec.marks;
			if (t.contentMatch = n[r] || (n[r] = Oo.parse(r, this.nodes)), t.inlineContent = t.contentMatch.inlineContent, t.spec.linebreakReplacement) {
				if (this.linebreakReplacement) throw RangeError("Multiple linebreak nodes defined");
				if (!t.isInline || !t.isLeaf) throw RangeError("Linebreak replacement nodes must be inline leaf nodes");
				this.linebreakReplacement = t;
			}
			t.markSet = i == "_" ? null : i ? Xo(this, i.split(" ")) : i == "" || !t.inlineContent ? [] : null;
		}
		for (let e in this.marks) {
			let t = this.marks[e], n = t.spec.excludes;
			t.excluded = n == null ? [t] : n == "" ? [] : Xo(this, n.split(" "));
		}
		this.nodeFromJSON = (e) => wo.fromJSON(this, e), this.markFromJSON = (e) => L.fromJSON(this, e), this.topNodeType = this.nodes[this.spec.topNode || "doc"], this.cached.wrappings = Object.create(null);
	}
	node(e, t = null, n, r) {
		if (typeof e == "string") e = this.nodeType(e);
		else if (!(e instanceof Go)) throw RangeError("Invalid node type: " + e);
		else if (e.schema != this) throw RangeError("Node type from different schema used (" + e.name + ")");
		return e.createChecked(t, n, r);
	}
	text(e, t) {
		let n = this.nodes.text;
		return new To(n, n.defaultAttrs, e, L.setFrom(t));
	}
	mark(e, t) {
		return typeof e == "string" && (e = this.marks[e]), e.create(t);
	}
	nodeType(e) {
		let t = this.nodes[e];
		if (!t) throw RangeError("Unknown node type: " + e);
		return t;
	}
};
function Xo(e, t) {
	let n = [];
	for (let r = 0; r < t.length; r++) {
		let i = t[r], a = e.marks[i], o = a;
		if (a) n.push(a);
		else for (let t in e.marks) {
			let r = e.marks[t];
			(i == "_" || r.spec.group && r.spec.group.split(" ").indexOf(i) > -1) && n.push(o = r);
		}
		if (!o) throw SyntaxError("Unknown mark type: '" + t[r] + "'");
	}
	return n;
}
function Zo(e) {
	return e.tag != null;
}
function Qo(e) {
	return e.style != null;
}
var $o = class e {
	constructor(e, t) {
		this.schema = e, this.rules = t, this.tags = [], this.styles = [];
		let n = this.matchedStyles = [];
		t.forEach((e) => {
			if (Zo(e)) this.tags.push(e);
			else if (Qo(e)) {
				let t = /[^=]*/.exec(e.style)[0];
				n.indexOf(t) < 0 && n.push(t), this.styles.push(e);
			}
		}), this.normalizeLists = !this.tags.some((t) => {
			if (!/^(ul|ol)\b/.test(t.tag) || !t.node) return !1;
			let n = e.nodes[t.node];
			return n.contentMatch.matchType(n);
		});
	}
	parse(e, t = {}) {
		let n = new ls(this, t, !1);
		return n.addAll(e, L.none, t.from, t.to), n.finish();
	}
	parseSlice(e, t = {}) {
		let n = new ls(this, t, !0);
		return n.addAll(e, L.none, t.from, t.to), R.maxOpen(n.finish());
	}
	matchTag(e, t, n) {
		for (let r = n ? this.tags.indexOf(n) + 1 : 0; r < this.tags.length; r++) {
			let n = this.tags[r];
			if (ds(e, n.tag) && (n.namespace === void 0 || e.namespaceURI == n.namespace) && (!n.context || t.matchesContext(n.context))) {
				if (n.getAttrs) {
					let t = n.getAttrs(e);
					if (t === !1) continue;
					n.attrs = t || void 0;
				}
				return n;
			}
		}
	}
	matchStyle(e, t, n, r) {
		for (let i = r ? this.styles.indexOf(r) + 1 : 0; i < this.styles.length; i++) {
			let r = this.styles[i], a = r.style;
			if (!(a.indexOf(e) != 0 || r.context && !n.matchesContext(r.context) || a.length > e.length && (a.charCodeAt(e.length) != 61 || a.slice(e.length + 1) != t))) {
				if (r.getAttrs) {
					let e = r.getAttrs(t);
					if (e === !1) continue;
					r.attrs = e || void 0;
				}
				return r;
			}
		}
	}
	static schemaRules(e) {
		let t = [];
		function n(e) {
			let n = e.priority == null ? 50 : e.priority, r = 0;
			for (; r < t.length; r++) {
				let e = t[r];
				if ((e.priority == null ? 50 : e.priority) < n) break;
			}
			t.splice(r, 0, e);
		}
		for (let t in e.marks) {
			let r = e.marks[t].spec.parseDOM;
			r && r.forEach((e) => {
				n(e = fs(e)), e.mark || e.ignore || e.clearMark || (e.mark = t);
			});
		}
		for (let t in e.nodes) {
			let r = e.nodes[t].spec.parseDOM;
			r && r.forEach((e) => {
				n(e = fs(e)), e.node || e.ignore || e.mark || (e.node = t);
			});
		}
		return t;
	}
	static fromSchema(t) {
		return t.cached.domParser || (t.cached.domParser = new e(t, e.schemaRules(t)));
	}
}, es = {
	address: !0,
	article: !0,
	aside: !0,
	blockquote: !0,
	body: !0,
	canvas: !0,
	dd: !0,
	div: !0,
	dl: !0,
	fieldset: !0,
	figcaption: !0,
	figure: !0,
	footer: !0,
	form: !0,
	h1: !0,
	h2: !0,
	h3: !0,
	h4: !0,
	h5: !0,
	h6: !0,
	header: !0,
	hgroup: !0,
	hr: !0,
	li: !0,
	noscript: !0,
	ol: !0,
	output: !0,
	p: !0,
	pre: !0,
	section: !0,
	table: !0,
	tfoot: !0,
	ul: !0
}, ts = {
	head: !0,
	noscript: !0,
	object: !0,
	script: !0,
	style: !0,
	title: !0
}, ns = {
	ol: !0,
	ul: !0
}, rs = 1, is = 2, as = 4;
function ss(e, t, n) {
	return t == null ? e && e.whitespace == "pre" ? 3 : n & -5 : (t ? rs : 0) | (t === "full" ? is : 0);
}
var cs = class {
	constructor(e, t, n, r, i, a) {
		this.type = e, this.attrs = t, this.marks = n, this.solid = r, this.options = a, this.content = [], this.activeMarks = L.none, this.match = i || (a & as ? null : e.contentMatch);
	}
	findWrapping(e) {
		if (!this.match) {
			if (!this.type) return [];
			let t = this.type.contentMatch.fillBefore(I.from(e));
			if (t) this.match = this.type.contentMatch.matchFragment(t);
			else {
				let t = this.type.contentMatch, n;
				return (n = t.findWrapping(e.type)) ? (this.match = t, n) : null;
			}
		}
		return this.match.findWrapping(e.type);
	}
	finish(e) {
		if (!(this.options & rs)) {
			let e = this.content[this.content.length - 1], t;
			if (e && e.isText && (t = /[ \t\r\n\u000c]+$/.exec(e.text))) {
				let n = e;
				e.text.length == t[0].length ? this.content.pop() : this.content[this.content.length - 1] = n.withText(n.text.slice(0, n.text.length - t[0].length));
			}
		}
		let t = I.from(this.content);
		return !e && this.match && (t = t.append(this.match.fillBefore(I.empty, !0))), this.type ? this.type.create(this.attrs, t, this.marks) : t;
	}
	inlineContext(e) {
		return this.type ? this.type.inlineContent : this.content.length ? this.content[0].isInline : e.parentNode && !es.hasOwnProperty(e.parentNode.nodeName.toLowerCase());
	}
}, ls = class {
	constructor(e, t, n) {
		this.parser = e, this.options = t, this.isOpen = n, this.open = 0, this.localPreserveWS = !1;
		let r = t.topNode, i, a = ss(null, t.preserveWhitespace, 0) | (n ? as : 0);
		i = r ? new cs(r.type, r.attrs, L.none, !0, t.topMatch || r.type.contentMatch, a) : n ? new cs(null, null, L.none, !0, null, a) : new cs(e.schema.topNodeType, null, L.none, !0, null, a), this.nodes = [i], this.find = t.findPositions, this.needsBlock = !1;
	}
	get top() {
		return this.nodes[this.open];
	}
	addDOM(e, t) {
		e.nodeType == 3 ? this.addTextNode(e, t) : e.nodeType == 1 && this.addElement(e, t);
	}
	addTextNode(e, t) {
		let n = e.nodeValue, r = this.top, i = r.options & is ? "full" : this.localPreserveWS || (r.options & rs) > 0, { schema: a } = this.parser;
		if (i === "full" || r.inlineContext(e) || /[^ \t\r\n\u000c]/.test(n)) {
			if (!i) {
				if (n = n.replace(/[ \t\r\n\u000c]+/g, " "), /^[ \t\r\n\u000c]/.test(n) && this.open == this.nodes.length - 1) {
					let t = r.content[r.content.length - 1], i = e.previousSibling;
					(!t || i && i.nodeName == "BR" || t.isText && /[ \t\r\n\u000c]$/.test(t.text)) && (n = n.slice(1));
				}
			} else if (i === "full") n = n.replace(/\r\n?/g, "\n");
			else if (a.linebreakReplacement && /[\r\n]/.test(n) && this.top.findWrapping(a.linebreakReplacement.create())) {
				let e = n.split(/\r?\n|\r/);
				for (let n = 0; n < e.length; n++) n && this.insertNode(a.linebreakReplacement.create(), t, !0), e[n] && this.insertNode(a.text(e[n]), t, !/\S/.test(e[n]));
				n = "";
			} else n = n.replace(/\r?\n|\r/g, " ");
			n && this.insertNode(a.text(n), t, !/\S/.test(n)), this.findInText(e);
		} else this.findInside(e);
	}
	addElement(e, t, n) {
		let r = this.localPreserveWS, i = this.top;
		(e.tagName == "PRE" || /pre/.test(e.style && e.style.whiteSpace)) && (this.localPreserveWS = !0);
		let a = e.nodeName.toLowerCase(), o;
		ns.hasOwnProperty(a) && this.parser.normalizeLists && us(e);
		let s = this.options.ruleFromNode && this.options.ruleFromNode(e) || (o = this.parser.matchTag(e, this, n));
		out: if (s ? s.ignore : ts.hasOwnProperty(a)) this.findInside(e), this.ignoreFallback(e, t);
		else if (!s || s.skip || s.closeParent) {
			s && s.closeParent ? this.open = Math.max(0, this.open - 1) : s && s.skip.nodeType && (e = s.skip);
			let n, r = this.needsBlock;
			if (es.hasOwnProperty(a)) i.content.length && i.content[0].isInline && this.open && (this.open--, i = this.top), n = !0, i.type || (this.needsBlock = !0);
			else if (!e.firstChild) {
				this.leafFallback(e, t);
				break out;
			}
			let o = s && s.skip ? t : this.readStyles(e, t);
			o && this.addAll(e, o), n && this.sync(i), this.needsBlock = r;
		} else {
			let n = this.readStyles(e, t);
			n && this.addElementByRule(e, s, n, s.consuming === !1 ? o : void 0);
		}
		this.localPreserveWS = r;
	}
	leafFallback(e, t) {
		e.nodeName == "BR" && this.top.type && this.top.type.inlineContent && this.addTextNode(e.ownerDocument.createTextNode("\n"), t);
	}
	ignoreFallback(e, t) {
		e.nodeName != "BR" || this.top.type && this.top.type.inlineContent || this.findPlace(this.parser.schema.text("-"), t, !0);
	}
	readStyles(e, t) {
		let n = e.style;
		if (n && n.length) for (let e = 0; e < this.parser.matchedStyles.length; e++) {
			let r = this.parser.matchedStyles[e], i = n.getPropertyValue(r);
			if (i) for (let e;;) {
				let n = this.parser.matchStyle(r, i, this, e);
				if (!n) break;
				if (n.ignore) return null;
				if (t = n.clearMark ? t.filter((e) => !n.clearMark(e)) : t.concat(this.parser.schema.marks[n.mark].create(n.attrs)), n.consuming === !1) e = n;
				else break;
			}
		}
		return t;
	}
	addElementByRule(e, t, n, r) {
		let i, a;
		if (t.node) {
			if (a = this.parser.schema.nodes[t.node], a.isLeaf) this.insertNode(a.create(t.attrs), n, e.nodeName == "BR") || this.leafFallback(e, n);
			else {
				let e = this.enter(a, t.attrs || null, n, t.preserveWhitespace);
				e && (i = !0, n = e);
			}
		} else {
			let e = this.parser.schema.marks[t.mark];
			n = n.concat(e.create(t.attrs));
		}
		let o = this.top;
		if (a && a.isLeaf) this.findInside(e);
		else if (r) this.addElement(e, n, r);
		else if (t.getContent) this.findInside(e), t.getContent(e, this.parser.schema).forEach((e) => this.insertNode(e, n, !1));
		else {
			let r = e;
			typeof t.contentElement == "string" ? r = e.querySelector(t.contentElement) : typeof t.contentElement == "function" ? r = t.contentElement(e) : t.contentElement && (r = t.contentElement), this.findAround(e, r, !0), this.addAll(r, n), this.findAround(e, r, !1);
		}
		i && this.sync(o) && this.open--;
	}
	addAll(e, t, n, r) {
		let i = n || 0;
		for (let a = n ? e.childNodes[n] : e.firstChild, o = r == null ? null : e.childNodes[r]; a != o; a = a.nextSibling, ++i) this.findAtPoint(e, i), this.addDOM(a, t);
		this.findAtPoint(e, i);
	}
	findPlace(e, t, n) {
		let r, i;
		for (let t = this.open, a = 0; t >= 0; t--) {
			let o = this.nodes[t], s = o.findWrapping(e);
			if (s && (!r || r.length > s.length + a) && (r = s, i = o, !s.length)) break;
			if (o.solid) {
				if (n) break;
				a += 2;
			}
		}
		if (!r) return null;
		this.sync(i);
		for (let e = 0; e < r.length; e++) t = this.enterInner(r[e], null, t, !1);
		return t;
	}
	insertNode(e, t, n) {
		if (e.isInline && this.needsBlock && !this.top.type) {
			let e = this.textblockFromContext();
			e && (t = this.enterInner(e, null, t));
		}
		let r = this.findPlace(e, t, n);
		if (r) {
			this.closeExtra();
			let t = this.top;
			t.match &&= t.match.matchType(e.type);
			let n = L.none;
			for (let i of r.concat(e.marks)) (t.type ? t.type.allowsMarkType(i.type) : ps(i.type, e.type)) && (n = i.addToSet(n));
			return t.content.push(e.mark(n)), !0;
		}
		return !1;
	}
	enter(e, t, n, r) {
		let i = this.findPlace(e.create(t), n, !1);
		return i &&= this.enterInner(e, t, n, !0, r), i;
	}
	enterInner(e, t, n, r = !1, i) {
		this.closeExtra();
		let a = this.top;
		a.match = a.match && a.match.matchType(e);
		let o = ss(e, i, a.options);
		a.options & as && a.content.length == 0 && (o |= as);
		let s = L.none;
		return n = n.filter((t) => !(a.type ? a.type.allowsMarkType(t.type) : ps(t.type, e)) || (s = t.addToSet(s), !1)), this.nodes.push(new cs(e, t, s, r, null, o)), this.open++, n;
	}
	closeExtra(e = !1) {
		let t = this.nodes.length - 1;
		if (t > this.open) {
			for (; t > this.open; t--) this.nodes[t - 1].content.push(this.nodes[t].finish(e));
			this.nodes.length = this.open + 1;
		}
	}
	finish() {
		return this.open = 0, this.closeExtra(this.isOpen), this.nodes[0].finish(!!(this.isOpen || this.options.topOpen));
	}
	sync(e) {
		for (let t = this.open; t >= 0; t--) if (this.nodes[t] == e) return this.open = t, !0;
		else this.localPreserveWS && (this.nodes[t].options |= rs);
		return !1;
	}
	get currentPos() {
		this.closeExtra();
		let e = 0;
		for (let t = this.open; t >= 0; t--) {
			let n = this.nodes[t].content;
			for (let t = n.length - 1; t >= 0; t--) e += n[t].nodeSize;
			t && e++;
		}
		return e;
	}
	findAtPoint(e, t) {
		if (this.find) for (let n = 0; n < this.find.length; n++) this.find[n].node == e && this.find[n].offset == t && (this.find[n].pos = this.currentPos);
	}
	findInside(e) {
		if (this.find) for (let t = 0; t < this.find.length; t++) this.find[t].pos == null && e.nodeType == 1 && e.contains(this.find[t].node) && (this.find[t].pos = this.currentPos);
	}
	findAround(e, t, n) {
		if (e != t && this.find) for (let r = 0; r < this.find.length; r++) this.find[r].pos == null && e.nodeType == 1 && e.contains(this.find[r].node) && t.compareDocumentPosition(this.find[r].node) & (n ? 2 : 4) && (this.find[r].pos = this.currentPos);
	}
	findInText(e) {
		if (this.find) for (let t = 0; t < this.find.length; t++) this.find[t].node == e && (this.find[t].pos = this.currentPos - (e.nodeValue.length - this.find[t].offset));
	}
	matchesContext(e) {
		if (e.indexOf("|") > -1) return e.split(/\s*\|\s*/).some(this.matchesContext, this);
		let t = e.split("/"), n = this.options.context, r = !this.isOpen && (!n || n.parent.type == this.nodes[0].type), i = -(n ? n.depth + 1 : 0) + +!r, a = (e, o) => {
			for (; e >= 0; e--) {
				let s = t[e];
				if (s == "") {
					if (e == t.length - 1 || e == 0) continue;
					for (; o >= i; o--) if (a(e - 1, o)) return !0;
					return !1;
				}
				{
					let e = o > 0 || o == 0 && r ? this.nodes[o].type : n && o >= i ? n.node(o - i).type : null;
					if (!e || e.name != s && !e.isInGroup(s)) return !1;
					o--;
				}
			}
			return !0;
		};
		return a(t.length - 1, this.open);
	}
	textblockFromContext() {
		let e = this.options.context;
		if (e) for (let t = e.depth; t >= 0; t--) {
			let n = e.node(t).contentMatchAt(e.indexAfter(t)).defaultType;
			if (n && n.isTextblock && n.defaultAttrs) return n;
		}
		for (let e in this.parser.schema.nodes) {
			let t = this.parser.schema.nodes[e];
			if (t.isTextblock && t.defaultAttrs) return t;
		}
	}
};
function us(e) {
	for (let t = e.firstChild, n = null; t; t = t.nextSibling) {
		let e = t.nodeType == 1 ? t.nodeName.toLowerCase() : null;
		e && ns.hasOwnProperty(e) && n ? (n.appendChild(t), t = n) : e == "li" ? n = t : e && (n = null);
	}
}
function ds(e, t) {
	return (e.matches || e.msMatchesSelector || e.webkitMatchesSelector || e.mozMatchesSelector).call(e, t);
}
function fs(e) {
	let t = {};
	for (let n in e) t[n] = e[n];
	return t;
}
function ps(e, t) {
	let n = t.schema.nodes;
	for (let r in n) {
		let i = n[r];
		if (!i.allowsMarkType(e)) continue;
		let a = [], o = (e) => {
			a.push(e);
			for (let n = 0; n < e.edgeCount; n++) {
				let { type: r, next: i } = e.edge(n);
				if (r == t || a.indexOf(i) < 0 && o(i)) return !0;
			}
		};
		if (o(i.contentMatch)) return !0;
	}
}
var ms = class e {
	constructor(e, t) {
		this.nodes = e, this.marks = t;
	}
	serializeFragment(e, t = {}, n) {
		n ||= gs(t).createDocumentFragment();
		let r = n, i = [];
		return e.forEach((e) => {
			if (i.length || e.marks.length) {
				let n = 0, a = 0;
				for (; n < i.length && a < e.marks.length;) {
					let t = e.marks[a];
					if (!this.marks[t.type.name]) a++;
					else {
						if (!t.eq(i[n][0]) || t.type.spec.spanning === !1) break;
						n++, a++;
					}
				}
				for (; n < i.length;) r = i.pop()[1];
				for (; a < e.marks.length;) {
					let n = e.marks[a++], o = this.serializeMark(n, e.isInline, t);
					o && (i.push([n, r]), r.appendChild(o.dom), r = o.contentDOM || o.dom);
				}
			}
			r.appendChild(this.serializeNodeInner(e, t));
		}), n;
	}
	serializeNodeInner(e, t) {
		if (e.isText) return gs(t).createTextNode(e.text);
		let { dom: n, contentDOM: r } = bs(gs(t), this.nodes[e.type.name](e), null, e.attrs);
		if (r) {
			if (e.isLeaf) throw RangeError("Content hole not allowed in a leaf node spec");
			this.serializeFragment(e.content, t, r);
		}
		return n;
	}
	serializeNode(e, t = {}) {
		let n = this.serializeNodeInner(e, t);
		for (let r = e.marks.length - 1; r >= 0; r--) {
			let i = this.serializeMark(e.marks[r], e.isInline, t);
			i && ((i.contentDOM || i.dom).appendChild(n), n = i.dom);
		}
		return n;
	}
	serializeMark(e, t, n = {}) {
		let r = this.marks[e.type.name];
		return r && bs(gs(n), r(e, t), null, e.attrs);
	}
	static renderSpec(e, t, n = null, r) {
		return typeof t == "string" ? { dom: e.createTextNode(t) } : bs(e, t, n, r);
	}
	static fromSchema(t) {
		return t.cached.domSerializer || (t.cached.domSerializer = new e(this.nodesFromSchema(t), this.marksFromSchema(t)));
	}
	static nodesFromSchema(e) {
		let t = hs(e.nodes);
		return t.text ||= (e) => e.text, t;
	}
	static marksFromSchema(e) {
		return hs(e.marks);
	}
};
function hs(e) {
	let t = {};
	for (let n in e) {
		let r = e[n].spec.toDOM;
		r && (t[n] = r);
	}
	return t;
}
function gs(e) {
	return e.document || window.document;
}
var _s = /* @__PURE__ */ new WeakMap();
function vs(e) {
	let t = _s.get(e);
	return t === void 0 && _s.set(e, t = ys(e)), t;
}
function ys(e) {
	let t = null;
	function n(e) {
		if (e && typeof e == "object") {
			if (Array.isArray(e)) {
				if (typeof e[0] == "string") t ||= [], t.push(e);
				else for (let t = 0; t < e.length; t++) n(e[t]);
			} else for (let t in e) n(e[t]);
		}
	}
	return n(e), t;
}
function bs(e, t, n, r) {
	if (t.nodeType == 1) return { dom: t };
	if (t.dom && t.dom.nodeType == 1) return t;
	let i = t[0], a;
	if (typeof i != "string") throw RangeError("Invalid array passed to renderSpec");
	if (r && (a = vs(r)) && a.indexOf(t) > -1) throw RangeError("Using an array from an attribute object as a DOM spec. This may be an attempted cross site scripting attack.");
	let o = i.indexOf(" ");
	o > 0 && (n = i.slice(0, o), i = i.slice(o + 1));
	let s, c = n ? e.createElementNS(n, i) : e.createElement(i), l = t[1], u = 1;
	if (l && typeof l == "object" && l.nodeType == null && !Array.isArray(l)) {
		u = 2;
		for (let e in l) if (l[e] != null) {
			let t = e.indexOf(" ");
			t > 0 ? c.setAttributeNS(e.slice(0, t), e.slice(t + 1), l[e]) : e == "style" && c.style ? c.style.cssText = l[e] : c.setAttribute(e, l[e]);
		}
	}
	for (let i = u; i < t.length; i++) {
		let a = t[i];
		if (a === 0) {
			if (i < t.length - 1 || i > u) throw RangeError("Content hole must be the only child of its parent node");
			return {
				dom: c,
				contentDOM: c
			};
		}
		if (typeof a == "string") c.appendChild(e.createTextNode(a));
		else {
			let { dom: t, contentDOM: i } = bs(e, a, n, r);
			if (c.appendChild(t), i) {
				if (s) throw RangeError("Multiple content holes");
				s = i;
			}
		}
	}
	return {
		dom: c,
		contentDOM: s
	};
}
// node_modules/.pnpm/prosemirror-transform@1.12.4/node_modules/prosemirror-transform/dist/index.js
var xs = 65535, Ss = 2 ** 16;
function Cs(e, t) {
	return e + t * Ss;
}
function ws(e) {
	return e & xs;
}
function Ts(e) {
	return (e - (e & xs)) / Ss;
}
var Es = 1, Ds = 2, Os = 4, ks = 8, As = class {
	constructor(e, t, n) {
		this.pos = e, this.delInfo = t, this.recover = n;
	}
	get deleted() {
		return (this.delInfo & ks) > 0;
	}
	get deletedBefore() {
		return (this.delInfo & 5) > 0;
	}
	get deletedAfter() {
		return (this.delInfo & 6) > 0;
	}
	get deletedAcross() {
		return (this.delInfo & Os) > 0;
	}
}, js = class e {
	constructor(t, n = !1) {
		if (this.ranges = t, this.inverted = n, !t.length && e.empty) return e.empty;
	}
	recover(e) {
		let t = 0, n = ws(e);
		if (!this.inverted) for (let e = 0; e < n; e++) t += this.ranges[e * 3 + 2] - this.ranges[e * 3 + 1];
		return this.ranges[n * 3] + t + Ts(e);
	}
	mapResult(e, t = 1) {
		return this._map(e, t, !1);
	}
	map(e, t = 1) {
		return this._map(e, t, !0);
	}
	_map(e, t, n) {
		let r = 0, i = this.inverted ? 2 : 1, a = this.inverted ? 1 : 2;
		for (let o = 0; o < this.ranges.length; o += 3) {
			let s = this.ranges[o] - (this.inverted ? r : 0);
			if (s > e) break;
			let c = this.ranges[o + i], l = this.ranges[o + a], u = s + c;
			if (e <= u) {
				let i = c ? e == s ? -1 : e == u ? 1 : t : t, a = s + r + (i < 0 ? 0 : l);
				if (n) return a;
				let d = e == (t < 0 ? s : u) ? null : Cs(o / 3, e - s), f = 0;
				return c && (f |= e == s ? Ds : e == u ? Es : Os, (t < 0 ? e != s : e != u) && (f |= ks)), new As(a, f, d);
			}
			r += l - c;
		}
		return n ? e + r : new As(e + r, 0, null);
	}
	touches(e, t) {
		let n = 0, r = ws(t), i = this.inverted ? 2 : 1, a = this.inverted ? 1 : 2;
		for (let t = 0; t < this.ranges.length; t += 3) {
			let o = this.ranges[t] - (this.inverted ? n : 0);
			if (o > e) break;
			let s = this.ranges[t + i];
			if (e <= o + s && t == r * 3) return !0;
			n += this.ranges[t + a] - s;
		}
		return !1;
	}
	forEach(e) {
		let t = this.inverted ? 2 : 1, n = this.inverted ? 1 : 2;
		for (let r = 0, i = 0; r < this.ranges.length; r += 3) {
			let a = this.ranges[r], o = a - (this.inverted ? i : 0), s = a + (this.inverted ? 0 : i), c = this.ranges[r + t], l = this.ranges[r + n];
			e(o, o + c, s, s + l), i += l - c;
		}
	}
	invert() {
		return new e(this.ranges, !this.inverted);
	}
	toString() {
		return (this.inverted ? "-" : "") + JSON.stringify(this.ranges);
	}
	static offset(t) {
		return t == 0 ? e.empty : new e(t < 0 ? [
			0,
			-t,
			0
		] : [
			0,
			0,
			t
		]);
	}
};
js.empty = new js([]);
var Ms = class e {
	constructor(e, t, n = 0, r = e ? e.length : 0) {
		this.mirror = t, this.from = n, this.to = r, this._maps = e || [], this.ownData = !(e || t);
	}
	get maps() {
		return this._maps;
	}
	slice(t = 0, n = this.maps.length) {
		return new e(this._maps, this.mirror, t, n);
	}
	appendMap(e, t) {
		this.ownData ||= (this._maps = this._maps.slice(), this.mirror = this.mirror && this.mirror.slice(), !0), this.to = this._maps.push(e), t != null && this.setMirror(this._maps.length - 1, t);
	}
	appendMapping(e) {
		for (let t = 0, n = this._maps.length; t < e._maps.length; t++) {
			let r = e.getMirror(t);
			this.appendMap(e._maps[t], r != null && r < t ? n + r : void 0);
		}
	}
	getMirror(e) {
		if (this.mirror) {
			for (let t = 0; t < this.mirror.length; t++) if (this.mirror[t] == e) return this.mirror[t + (t % 2 ? -1 : 1)];
		}
	}
	setMirror(e, t) {
		this.mirror ||= [], this.mirror.push(e, t);
	}
	appendMappingInverted(e) {
		for (let t = e.maps.length - 1, n = this._maps.length + e._maps.length; t >= 0; t--) {
			let r = e.getMirror(t);
			this.appendMap(e._maps[t].invert(), r != null && r > t ? n - r - 1 : void 0);
		}
	}
	invert() {
		let t = new e();
		return t.appendMappingInverted(this), t;
	}
	map(e, t = 1) {
		if (this.mirror) return this._map(e, t, !0);
		for (let n = this.from; n < this.to; n++) e = this._maps[n].map(e, t);
		return e;
	}
	mapResult(e, t = 1) {
		return this._map(e, t, !1);
	}
	_map(e, t, n) {
		let r = 0;
		for (let n = this.from; n < this.to; n++) {
			let i = this._maps[n].mapResult(e, t);
			if (i.recover != null) {
				let t = this.getMirror(n);
				if (t != null && t > n && t < this.to) {
					n = t, e = this._maps[t].recover(i.recover);
					continue;
				}
			}
			r |= i.delInfo, e = i.pos;
		}
		return n ? e : new As(e, r, null);
	}
}, Ns = Object.create(null), z = class {
	getMap() {
		return js.empty;
	}
	merge(e) {
		return null;
	}
	static fromJSON(e, t) {
		if (!t || !t.stepType) throw RangeError("Invalid input for Step.fromJSON");
		let n = Ns[t.stepType];
		if (!n) throw RangeError(`No step type ${t.stepType} defined`);
		return n.fromJSON(e, t);
	}
	static jsonID(e, t) {
		if (e in Ns) throw RangeError("Duplicate use of step JSON ID " + e);
		return Ns[e] = t, t.prototype.jsonID = e, t;
	}
}, B = class e {
	constructor(e, t) {
		this.doc = e, this.failed = t;
	}
	static ok(t) {
		return new e(t, null);
	}
	static fail(t) {
		return new e(null, t);
	}
	static fromReplace(t, n, r, i) {
		try {
			return e.ok(t.replace(n, r, i));
		} catch (t) {
			if (t instanceof io) return e.fail(t.message);
			throw t;
		}
	}
};
function Ps(e, t, n) {
	let r = [];
	for (let i = 0; i < e.childCount; i++) {
		let a = e.child(i);
		a.content.size && (a = a.copy(Ps(a.content, t, a))), a.isInline && (a = t(a, n, i)), r.push(a);
	}
	return I.fromArray(r);
}
var Fs = class e extends z {
	constructor(e, t, n) {
		super(), this.from = e, this.to = t, this.mark = n;
	}
	apply(e) {
		let t = e.slice(this.from, this.to), n = e.resolve(this.from), r = n.node(n.sharedDepth(this.to)), i = new R(Ps(t.content, (e, t) => !e.isAtom || !t.type.allowsMarkType(this.mark.type) ? e : e.mark(this.mark.addToSet(e.marks)), r), t.openStart, t.openEnd);
		return B.fromReplace(e, this.from, this.to, i);
	}
	invert(t) {
		let n = [];
		return t.nodesBetween(this.from, this.to, (t, r) => {
			if (t.isInline && t.isAtom) {
				let i = this.mark.addToSet(t.marks);
				if (!L.sameSet(i, t.marks)) {
					let a = Math.max(this.from, r), o = Math.min(this.to, r + t.nodeSize);
					Ls(Is, a, o, this.mark, n);
					for (let r of t.marks) r.isInSet(i) || Ls(e, a, o, r, n);
				}
			}
		}), n.length == 1 ? n[0] : new Rs(n);
	}
	map(t) {
		let n = t.map(this.from, 1), r = t.map(this.to, -1);
		return n >= r ? null : new e(n, r, this.mark);
	}
	merge(t) {
		return t instanceof e && t.mark.eq(this.mark) && this.from <= t.to && this.to >= t.from ? new e(Math.min(this.from, t.from), Math.max(this.to, t.to), this.mark) : null;
	}
	toJSON() {
		return {
			stepType: "addMark",
			mark: this.mark.toJSON(),
			from: this.from,
			to: this.to
		};
	}
	static fromJSON(t, n) {
		if (typeof n.from != "number" || typeof n.to != "number") throw RangeError("Invalid input for AddMarkStep.fromJSON");
		return new e(n.from, n.to, t.markFromJSON(n.mark));
	}
};
z.jsonID("addMark", Fs);
var Is = class e extends z {
	constructor(e, t, n) {
		super(), this.from = e, this.to = t, this.mark = n;
	}
	apply(e) {
		let t = e.slice(this.from, this.to), n = new R(Ps(t.content, (e) => e.mark(this.mark.removeFromSet(e.marks)), e), t.openStart, t.openEnd);
		return B.fromReplace(e, this.from, this.to, n);
	}
	invert(e) {
		let t = [];
		return e.nodesBetween(this.from, this.to, (e, n) => {
			e.isInline && e.isAtom && this.mark.isInSet(e.marks) && Ls(Fs, Math.max(this.from, n), Math.min(this.to, n + e.nodeSize), this.mark, t);
		}), t.length == 1 ? t[0] : new Rs(t);
	}
	map(t) {
		let n = t.map(this.from, 1), r = t.map(this.to, -1);
		return n >= r ? null : new e(n, r, this.mark);
	}
	merge(t) {
		return t instanceof e && t.mark.eq(this.mark) && this.from <= t.to && this.to >= t.from ? new e(Math.min(this.from, t.from), Math.max(this.to, t.to), this.mark) : null;
	}
	toJSON() {
		return {
			stepType: "removeMark",
			mark: this.mark.toJSON(),
			from: this.from,
			to: this.to
		};
	}
	static fromJSON(t, n) {
		if (typeof n.from != "number" || typeof n.to != "number") throw RangeError("Invalid input for RemoveMarkStep.fromJSON");
		return new e(n.from, n.to, t.markFromJSON(n.mark));
	}
};
z.jsonID("removeMark", Is);
function Ls(e, t, n, r, i) {
	let a = i.length ? i[i.length - 1] : null;
	a && a instanceof e && a.to == t && a.mark.eq(r) ? i[i.length - 1] = new e(a.from, n, r) : i.push(new e(t, n, r));
}
var Rs = class e extends z {
	constructor(e) {
		super(), this.steps = e;
	}
	apply(e) {
		for (let t of this.steps) {
			let n = t.apply(e);
			if (!n.doc) return n;
			e = n.doc;
		}
		return new B(e, null);
	}
	invert(t) {
		return new e(this.steps.map((e) => e.invert(t)));
	}
	map(t) {
		let n = [];
		for (let e of this.steps) {
			let r = e.map(t);
			r && n.push(r);
		}
		return n.length ? new e(n) : null;
	}
	toJSON() {
		return {
			stepType: "markSet",
			steps: this.steps.map((e) => e.toJSON())
		};
	}
	static fromJSON(t, n) {
		if (!Array.isArray(n.steps) || !n.steps.every((e) => e.stepType == "addMark" || e.stepType == "removeMark")) throw RangeError("Invalid JSON for MarkSetStep");
		return new e(n.steps.map((e) => z.fromJSON(t, e)));
	}
};
z.jsonID("markSet", Rs);
var zs = class e extends z {
	constructor(e, t) {
		super(), this.pos = e, this.mark = t;
	}
	apply(e) {
		let t = e.nodeAt(this.pos);
		if (!t) return B.fail("No node at mark step's position");
		let n = t.type.create(t.attrs, null, this.mark.addToSet(t.marks));
		return B.fromReplace(e, this.pos, this.pos + 1, new R(I.from(n), 0, +!t.isLeaf));
	}
	invert(t) {
		let n = t.nodeAt(this.pos);
		if (n) {
			let t = this.mark.addToSet(n.marks);
			if (t.length == n.marks.length) {
				for (let r = 0; r < n.marks.length; r++) if (!n.marks[r].isInSet(t)) return new e(this.pos, n.marks[r]);
				return new e(this.pos, this.mark);
			}
		}
		return new Bs(this.pos, this.mark);
	}
	map(t) {
		let n = t.mapResult(this.pos, 1);
		return n.deletedAfter ? null : new e(n.pos, this.mark);
	}
	toJSON() {
		return {
			stepType: "addNodeMark",
			pos: this.pos,
			mark: this.mark.toJSON()
		};
	}
	static fromJSON(t, n) {
		if (typeof n.pos != "number") throw RangeError("Invalid input for AddNodeMarkStep.fromJSON");
		return new e(n.pos, t.markFromJSON(n.mark));
	}
};
z.jsonID("addNodeMark", zs);
var Bs = class e extends z {
	constructor(e, t) {
		super(), this.pos = e, this.mark = t;
	}
	apply(e) {
		let t = e.nodeAt(this.pos);
		if (!t) return B.fail("No node at mark step's position");
		let n = t.type.create(t.attrs, null, this.mark.removeFromSet(t.marks));
		return B.fromReplace(e, this.pos, this.pos + 1, new R(I.from(n), 0, +!t.isLeaf));
	}
	invert(e) {
		let t = e.nodeAt(this.pos);
		return !t || !this.mark.isInSet(t.marks) ? this : new zs(this.pos, this.mark);
	}
	map(t) {
		let n = t.mapResult(this.pos, 1);
		return n.deletedAfter ? null : new e(n.pos, this.mark);
	}
	toJSON() {
		return {
			stepType: "removeNodeMark",
			pos: this.pos,
			mark: this.mark.toJSON()
		};
	}
	static fromJSON(t, n) {
		if (typeof n.pos != "number") throw RangeError("Invalid input for RemoveNodeMarkStep.fromJSON");
		return new e(n.pos, t.markFromJSON(n.mark));
	}
};
z.jsonID("removeNodeMark", Bs);
var Vs = class e extends z {
	constructor(e, t, n, r = !1) {
		super(), this.from = e, this.to = t, this.slice = n, this.structure = r;
	}
	apply(e) {
		return this.structure && Us(e, this.from, this.to) ? B.fail("Structure replace would overwrite content") : B.fromReplace(e, this.from, this.to, this.slice);
	}
	getMap() {
		return new js([
			this.from,
			this.to - this.from,
			this.slice.size
		]);
	}
	invert(t) {
		return new e(this.from, this.from + this.slice.size, t.slice(this.from, this.to));
	}
	map(t) {
		let n = t.mapResult(this.to, -1), r = this.from == this.to && e.MAP_BIAS < 0 ? n : t.mapResult(this.from, 1);
		return r.deletedAcross && n.deletedAcross ? null : new e(r.pos, Math.max(r.pos, n.pos), this.slice, this.structure);
	}
	merge(t) {
		if (!(t instanceof e) || t.structure || this.structure) return null;
		if (this.from + this.slice.size == t.from && !this.slice.openEnd && !t.slice.openStart) {
			let n = this.slice.size + t.slice.size == 0 ? R.empty : new R(this.slice.content.append(t.slice.content), this.slice.openStart, t.slice.openEnd);
			return new e(this.from, this.to + (t.to - t.from), n, this.structure);
		}
		if (t.to == this.from && !this.slice.openStart && !t.slice.openEnd) {
			let n = this.slice.size + t.slice.size == 0 ? R.empty : new R(t.slice.content.append(this.slice.content), t.slice.openStart, this.slice.openEnd);
			return new e(t.from, this.to, n, this.structure);
		}
		return null;
	}
	toJSON() {
		let e = {
			stepType: "replace",
			from: this.from,
			to: this.to
		};
		return this.slice.size && (e.slice = this.slice.toJSON()), this.structure && (e.structure = !0), e;
	}
	static fromJSON(t, n) {
		if (typeof n.from != "number" || typeof n.to != "number") throw RangeError("Invalid input for ReplaceStep.fromJSON");
		return new e(n.from, n.to, R.fromJSON(t, n.slice), !!n.structure);
	}
};
Vs.MAP_BIAS = 1, z.jsonID("replace", Vs);
var Hs = class e extends z {
	constructor(e, t, n, r, i, a, o = !1) {
		super(), this.from = e, this.to = t, this.gapFrom = n, this.gapTo = r, this.slice = i, this.insert = a, this.structure = o;
	}
	apply(e) {
		if (this.structure && (Us(e, this.from, this.gapFrom) || Us(e, this.gapTo, this.to))) return B.fail("Structure gap-replace would overwrite content");
		let t = e.slice(this.gapFrom, this.gapTo);
		if (t.openStart || t.openEnd) return B.fail("Gap is not a flat range");
		let n = this.slice.insertAt(this.insert, t.content);
		return n ? B.fromReplace(e, this.from, this.to, n) : B.fail("Content does not fit in gap");
	}
	getMap() {
		return new js([
			this.from,
			this.gapFrom - this.from,
			this.insert,
			this.gapTo,
			this.to - this.gapTo,
			this.slice.size - this.insert
		]);
	}
	invert(t) {
		let n = this.gapTo - this.gapFrom;
		return new e(this.from, this.from + this.slice.size + n, this.from + this.insert, this.from + this.insert + n, t.slice(this.from, this.to).removeBetween(this.gapFrom - this.from, this.gapTo - this.from), this.gapFrom - this.from, this.structure);
	}
	map(t) {
		let n = t.mapResult(this.from, 1), r = t.mapResult(this.to, -1), i = this.from == this.gapFrom ? n.pos : t.map(this.gapFrom, -1), a = this.to == this.gapTo ? r.pos : t.map(this.gapTo, 1);
		return n.deletedAcross && r.deletedAcross || i < n.pos || a > r.pos ? null : new e(n.pos, r.pos, i, a, this.slice, this.insert, this.structure);
	}
	toJSON() {
		let e = {
			stepType: "replaceAround",
			from: this.from,
			to: this.to,
			gapFrom: this.gapFrom,
			gapTo: this.gapTo,
			insert: this.insert
		};
		return this.slice.size && (e.slice = this.slice.toJSON()), this.structure && (e.structure = !0), e;
	}
	static fromJSON(t, n) {
		if (typeof n.from != "number" || typeof n.to != "number" || typeof n.gapFrom != "number" || typeof n.gapTo != "number" || typeof n.insert != "number") throw RangeError("Invalid input for ReplaceAroundStep.fromJSON");
		return new e(n.from, n.to, n.gapFrom, n.gapTo, R.fromJSON(t, n.slice), n.insert, !!n.structure);
	}
};
z.jsonID("replaceAround", Hs);
function Us(e, t, n) {
	let r = e.resolve(t), i = n - t, a = r.depth;
	for (; i > 0 && a > 0 && r.indexAfter(a) == r.node(a).childCount;) a--, i--;
	if (i > 0) {
		let e = r.node(a).maybeChild(r.indexAfter(a));
		for (; i > 0;) {
			if (!e || e.isLeaf) return !0;
			e = e.firstChild, i--;
		}
	}
	return !1;
}
function Ws(e, t, n, r) {
	let i = [];
	e.doc.nodesBetween(t, n, (e, a, o) => {
		if (!e.isInline) return;
		let s = e.marks;
		!r.isInSet(s) && o.type.allowsMarkType(r.type) && Ls(Fs, Math.max(a, t), Math.min(a + e.nodeSize, n), r, i);
	}), i.forEach((t) => e.step(t));
}
function Gs(e, t, n, r) {
	let i = [], a = 0;
	e.doc.nodesBetween(t, n, (e, o) => {
		if (!e.isInline || !e.isAtom) return;
		a++;
		let s = null;
		if (r instanceof Jo) {
			let t = e.marks, n;
			for (; n = r.isInSet(t);) (s ||= []).push(n), t = n.removeFromSet(t);
		} else r ? r.isInSet(e.marks) && (s = [r]) : s = e.marks;
		if (s && s.length) {
			let r = Math.min(o + e.nodeSize, n);
			for (let e = 0; e < s.length; e++) {
				let n = s[e], c;
				for (let e = 0; e < i.length; e++) {
					let t = i[e];
					t.step == a - 1 && n.eq(i[e].style) && (c = t);
				}
				c ? (c.to = r, c.step = a) : i.push({
					style: n,
					from: Math.max(o, t),
					to: r,
					step: a
				});
			}
		}
	}), i.forEach((t) => e.step(new Is(t.from, t.to, t.style)));
}
function Ks(e, t, n, r = n.contentMatch, i = !0) {
	let a = e.doc.nodeAt(t), o = [], s = t + 1;
	for (let t = 0; t < a.childCount; t++) {
		let c = a.child(t), l = s + c.nodeSize, u = r.matchType(c.type);
		if (!u) o.push(new Vs(s, l, R.empty));
		else {
			r = u;
			for (let t = 0; t < c.marks.length; t++) n.allowsMarkType(c.marks[t].type) || e.step(new Is(s, l, c.marks[t]));
			if (i && c.isText && n.whitespace != "pre") {
				let e, t = /\r?\n|\r/g, r;
				for (; e = t.exec(c.text);) r ||= new R(I.from(n.schema.text(" ", n.allowedMarks(c.marks))), 0, 0), o.push(new Vs(s + e.index, s + e.index + e[0].length, r));
			}
		}
		s = l;
	}
	if (!r.validEnd) {
		let t = r.fillBefore(I.empty, !0);
		e.replace(s, s, new R(t, 0, 0));
	}
	for (let t = o.length - 1; t >= 0; t--) e.step(o[t]);
}
function qs(e, t, n) {
	return (t == 0 || e.canReplace(t, e.childCount)) && (n == e.childCount || e.canReplace(0, n));
}
function Js(e) {
	let t = e.parent.content.cutByIndex(e.startIndex, e.endIndex);
	for (let n = e.depth, r = 0, i = 0;; --n) {
		let a = e.$from.node(n), o = e.$from.index(n) + r, s = e.$to.indexAfter(n) - i;
		if (n < e.depth && a.canReplace(o, s, t)) return n;
		if (n == 0 || a.type.spec.isolating || !qs(a, o, s)) break;
		o && (r = 1), s < a.childCount && (i = 1);
	}
	return null;
}
function Ys(e, t, n) {
	let { $from: r, $to: i, depth: a } = t, o = r.before(a + 1), s = i.after(a + 1), c = o, l = s, u = I.empty, d = 0;
	for (let e = a, t = !1; e > n; e--) t || r.index(e) > 0 ? (t = !0, u = I.from(r.node(e).copy(u)), d++) : c--;
	let f = I.empty, p = 0;
	for (let e = a, t = !1; e > n; e--) t || i.after(e + 1) < i.end(e) ? (t = !0, f = I.from(i.node(e).copy(f)), p++) : l++;
	e.step(new Hs(c, l, o, s, new R(u.append(f), d, p), u.size - d, !0));
}
function Xs(e, t, n = null, r = e) {
	let i = Qs(e, t), a = i && $s(r, t);
	return a ? i.map(Zs).concat({
		type: t,
		attrs: n
	}).concat(a.map(Zs)) : null;
}
function Zs(e) {
	return {
		type: e,
		attrs: null
	};
}
function Qs(e, t) {
	let { parent: n, startIndex: r, endIndex: i } = e, a = n.contentMatchAt(r).findWrapping(t);
	if (!a) return null;
	let o = a.length ? a[0] : t;
	return n.canReplaceWith(r, i, o) ? a : null;
}
function $s(e, t) {
	let { parent: n, startIndex: r, endIndex: i } = e, a = n.child(r), o = t.contentMatch.findWrapping(a.type);
	if (!o) return null;
	let s = (o.length ? o[o.length - 1] : t).contentMatch;
	for (let e = r; s && e < i; e++) s = s.matchType(n.child(e).type);
	return !s || !s.validEnd ? null : o;
}
function ec(e, t, n) {
	let r = I.empty;
	for (let e = n.length - 1; e >= 0; e--) {
		if (r.size) {
			let t = n[e].type.contentMatch.matchFragment(r);
			if (!t || !t.validEnd) throw RangeError("Wrapper type given to Transform.wrap does not form valid content of its parent wrapper");
		}
		r = I.from(n[e].type.create(n[e].attrs, r));
	}
	let i = t.start, a = t.end;
	e.step(new Hs(i, a, i, a, new R(r, 0, 0), n.length, !0));
}
function tc(e, t, n, r, i) {
	if (!r.isTextblock) throw RangeError("Type given to setBlockType should be a textblock");
	let a = e.steps.length;
	e.doc.nodesBetween(t, n, (t, n) => {
		let o = typeof i == "function" ? i(t) : i;
		if (t.isTextblock && !t.hasMarkup(r, o) && ic(e.doc, e.mapping.slice(a).map(n), r)) {
			let i = null;
			if (r.schema.linebreakReplacement) {
				let e = r.whitespace == "pre", t = !!r.contentMatch.matchType(r.schema.linebreakReplacement);
				e && !t ? i = !1 : !e && t && (i = !0);
			}
			i === !1 && rc(e, t, n, a), Ks(e, e.mapping.slice(a).map(n, 1), r, void 0, i === null);
			let s = e.mapping.slice(a), c = s.map(n, 1), l = s.map(n + t.nodeSize, 1);
			return e.step(new Hs(c, l, c + 1, l - 1, new R(I.from(r.create(o, null, t.marks)), 0, 0), 1, !0)), i === !0 && nc(e, t, n, a), !1;
		}
	});
}
function nc(e, t, n, r) {
	t.forEach((i, a) => {
		if (i.isText) {
			let o, s = /\r?\n|\r/g;
			for (; o = s.exec(i.text);) {
				let i = e.mapping.slice(r).map(n + 1 + a + o.index);
				e.replaceWith(i, i + 1, t.type.schema.linebreakReplacement.create());
			}
		}
	});
}
function rc(e, t, n, r) {
	t.forEach((i, a) => {
		if (i.type == i.type.schema.linebreakReplacement) {
			let i = e.mapping.slice(r).map(n + 1 + a);
			e.replaceWith(i, i + 1, t.type.schema.text("\n"));
		}
	});
}
function ic(e, t, n) {
	let r = e.resolve(t), i = r.index();
	return r.parent.canReplaceWith(i, i + 1, n);
}
function ac(e, t, n, r, i) {
	let a = e.doc.nodeAt(t);
	if (!a) throw RangeError("No node at given position");
	n ||= a.type;
	let o = n.create(r, null, i || a.marks);
	if (a.isLeaf) return e.replaceWith(t, t + a.nodeSize, o);
	if (!n.validContent(a.content)) throw RangeError("Invalid content for node type " + n.name);
	e.step(new Hs(t, t + a.nodeSize, t + 1, t + a.nodeSize - 1, new R(I.from(o), 0, 0), 1, !0));
}
function oc(e, t, n = 1, r) {
	let i = e.resolve(t), a = i.depth - n, o = r && r[r.length - 1] || i.parent;
	if (a < 0 || i.parent.type.spec.isolating || !i.parent.canReplace(i.index(), i.parent.childCount) || !o.type.validContent(i.parent.content.cutByIndex(i.index(), i.parent.childCount))) return !1;
	for (let e = i.depth - 1, t = n - 2; e > a; e--, t--) {
		let n = i.node(e), a = i.index(e);
		if (n.type.spec.isolating) return !1;
		let o = n.content.cutByIndex(a, n.childCount), s = r && r[t + 1];
		s && (o = o.replaceChild(0, s.type.create(s.attrs)));
		let c = r && r[t] || n;
		if (!n.canReplace(a + 1, n.childCount) || !c.type.validContent(o)) return !1;
	}
	let s = i.indexAfter(a), c = r && r[0];
	return i.node(a).canReplaceWith(s, s, c ? c.type : i.node(a + 1).type);
}
function sc(e, t, n = 1, r) {
	let i = e.doc.resolve(t), a = I.empty, o = I.empty;
	for (let e = i.depth, t = i.depth - n, s = n - 1; e > t; e--, s--) {
		a = I.from(i.node(e).copy(a));
		let t = r && r[s];
		o = I.from(t ? t.type.create(t.attrs, o) : i.node(e).copy(o));
	}
	e.step(new Vs(t, t, new R(a.append(o), n, n), !0));
}
function cc(e, t) {
	let n = e.resolve(t), r = n.index();
	return uc(n.nodeBefore, n.nodeAfter) && n.parent.canReplace(r, r + 1);
}
function lc(e, t) {
	t.content.size || e.type.compatibleContent(t.type);
	let n = e.contentMatchAt(e.childCount), { linebreakReplacement: r } = e.type.schema;
	for (let i = 0; i < t.childCount; i++) {
		let a = t.child(i), o = a.type == r ? e.type.schema.nodes.text : a.type;
		if (n = n.matchType(o), !n || !e.type.allowsMarks(a.marks)) return !1;
	}
	return n.validEnd;
}
function uc(e, t) {
	return !!(e && t && !e.isLeaf && lc(e, t));
}
function dc(e, t, n) {
	let r = null, { linebreakReplacement: i } = e.doc.type.schema, a = e.doc.resolve(t - n), o = a.node().type;
	if (i && o.inlineContent) {
		let e = o.whitespace == "pre", t = !!o.contentMatch.matchType(i);
		e && !t ? r = !1 : !e && t && (r = !0);
	}
	let s = e.steps.length;
	if (r === !1) {
		let r = e.doc.resolve(t + n);
		rc(e, r.node(), r.before(), s);
	}
	o.inlineContent && Ks(e, t + n - 1, o, a.node().contentMatchAt(a.index()), r == null);
	let c = e.mapping.slice(s), l = c.map(t - n);
	if (e.step(new Vs(l, c.map(t + n, -1), R.empty, !0)), r === !0) {
		let t = e.doc.resolve(l);
		nc(e, t.node(), t.before(), e.steps.length);
	}
	return e;
}
function fc(e, t, n) {
	let r = e.resolve(t);
	if (r.parent.canReplaceWith(r.index(), r.index(), n)) return t;
	if (r.parentOffset == 0) for (let e = r.depth - 1; e >= 0; e--) {
		let t = r.index(e);
		if (r.node(e).canReplaceWith(t, t, n)) return r.before(e + 1);
		if (t > 0) return null;
	}
	if (r.parentOffset == r.parent.content.size) for (let e = r.depth - 1; e >= 0; e--) {
		let t = r.indexAfter(e);
		if (r.node(e).canReplaceWith(t, t, n)) return r.after(e + 1);
		if (t < r.node(e).childCount) return null;
	}
	return null;
}
function pc(e, t, n) {
	let r = e.resolve(t);
	if (!n.content.size) return t;
	let i = n.content;
	for (let e = 0; e < n.openStart; e++) i = i.firstChild.content;
	for (let e = 1; e <= (n.openStart == 0 && n.size ? 2 : 1); e++) for (let t = r.depth; t >= 0; t--) {
		let n = t == r.depth ? 0 : r.pos <= (r.start(t + 1) + r.end(t + 1)) / 2 ? -1 : 1, a = r.index(t) + +(n > 0), o = r.node(t), s = !1;
		if (e == 1) s = o.canReplace(a, a, i);
		else {
			let e = o.contentMatchAt(a).findWrapping(i.firstChild.type);
			s = e && o.canReplaceWith(a, a, e[0]);
		}
		if (s) return n == 0 ? r.pos : n < 0 ? r.before(t + 1) : r.after(t + 1);
	}
	return null;
}
function mc(e, t, n = t, r = R.empty) {
	if (t == n && !r.size) return null;
	let i = e.resolve(t), a = e.resolve(n);
	return hc(i, a, r) ? new Vs(t, n, r) : new gc(i, a, r).fit();
}
function hc(e, t, n) {
	return !n.openStart && !n.openEnd && e.start() == t.start() && e.parent.canReplace(e.index(), t.index(), n.content);
}
var gc = class {
	constructor(e, t, n) {
		this.$from = e, this.$to = t, this.unplaced = n, this.frontier = [], this.placed = I.empty;
		for (let t = 0; t <= e.depth; t++) {
			let n = e.node(t);
			this.frontier.push({
				type: n.type,
				match: n.contentMatchAt(e.indexAfter(t))
			});
		}
		for (let t = e.depth; t > 0; t--) this.placed = I.from(e.node(t).copy(this.placed));
	}
	get depth() {
		return this.frontier.length - 1;
	}
	fit() {
		for (; this.unplaced.size;) {
			let e = this.findFittable();
			e ? this.placeNodes(e) : this.openMore() || this.dropNode();
		}
		let e = this.mustMoveInline(), t = this.placed.size - this.depth - this.$from.depth, n = this.$from, r = this.close(e < 0 ? this.$to : n.doc.resolve(e));
		if (!r) return null;
		let i = this.placed, a = n.depth, o = r.depth;
		for (; a && o && i.childCount == 1;) i = i.firstChild.content, a--, o--;
		let s = new R(i, a, o);
		return e > -1 ? new Hs(n.pos, e, this.$to.pos, this.$to.end(), s, t) : s.size || n.pos != this.$to.pos ? new Vs(n.pos, r.pos, s) : null;
	}
	findFittable() {
		let e = this.unplaced.openStart;
		for (let t = this.unplaced.content, n = 0, r = this.unplaced.openEnd; n < e; n++) {
			let i = t.firstChild;
			if (t.childCount > 1 && (r = 0), i.type.spec.isolating && r <= n) {
				e = n;
				break;
			}
			t = i.content;
		}
		for (let t = 1; t <= 2; t++) for (let n = t == 1 ? e : this.unplaced.openStart; n >= 0; n--) {
			let e, r = null;
			n ? (r = yc(this.unplaced.content, n - 1).firstChild, e = r.content) : e = this.unplaced.content;
			let i = e.firstChild;
			for (let e = this.depth; e >= 0; e--) {
				let { type: a, match: o } = this.frontier[e], s, c = null;
				if (t == 1 && (i ? o.matchType(i.type) || (c = o.fillBefore(I.from(i), !1)) : r && a.compatibleContent(r.type))) return {
					sliceDepth: n,
					frontierDepth: e,
					parent: r,
					inject: c
				};
				if (t == 2 && i && (s = o.findWrapping(i.type))) return {
					sliceDepth: n,
					frontierDepth: e,
					parent: r,
					wrap: s
				};
				if (r && o.matchType(r.type)) break;
			}
		}
	}
	openMore() {
		let { content: e, openStart: t, openEnd: n } = this.unplaced;
		return wc(e, -1) <= t ? !1 : (this.unplaced.size > 1 && wc(e, 1) > n && n++, this.unplaced = new R(e, t + 1, n), !0);
	}
	dropNode() {
		let { content: e, openStart: t, openEnd: n } = this.unplaced, r = yc(e, t);
		if (r.childCount <= 1 && t > 0) {
			let i = e.size - t <= t + r.size;
			this.unplaced = new R(_c(e, t - 1, 1), t - 1, i ? t - 1 : n);
		} else this.unplaced = new R(_c(e, t, 1), t, n);
	}
	placeNodes({ sliceDepth: e, frontierDepth: t, parent: n, inject: r, wrap: i }) {
		for (; this.depth > t;) this.closeFrontierNode();
		if (i) for (let e = 0; e < i.length; e++) this.openFrontierNode(i[e]);
		let a = this.unplaced, o = n ? n.content : a.content, s = a.openStart - e, c = 0, l = [], { match: u, type: d } = this.frontier[t];
		if (r) {
			for (let e = 0; e < r.childCount; e++) l.push(r.child(e));
			u = u.matchFragment(r);
		}
		let f = o.size + e - (a.content.size - a.openEnd);
		for (; c < o.childCount;) {
			let e = o.child(c), t = u.matchType(e.type);
			if (!t) break;
			c++, (c > 1 || s == 0 || e.content.size) && (u = t, l.push(bc(e.mark(d.allowedMarks(e.marks)), c == 1 ? s : 0, c == o.childCount ? f : -1)));
		}
		let p = c == o.childCount;
		p || (f = -1), this.placed = vc(this.placed, t, I.from(l)), this.frontier[t].match = u, p && f < 0 && n && n.type == this.frontier[this.depth].type && this.frontier.length > 1 && this.closeFrontierNode();
		for (let e = 0, t = o; e < f; e++) {
			let e = t.lastChild;
			this.frontier.push({
				type: e.type,
				match: e.contentMatchAt(e.childCount)
			}), t = e.content;
		}
		this.unplaced = p ? e == 0 ? R.empty : new R(_c(a.content, e - 1, 1), e - 1, f < 0 ? a.openEnd : e - 1) : new R(_c(a.content, e, c), a.openStart, a.openEnd);
	}
	mustMoveInline() {
		if (!this.$to.parent.isTextblock) return -1;
		let e = this.frontier[this.depth], t;
		if (!e.type.isTextblock || !xc(this.$to, this.$to.depth, e.type, e.match, !1) || this.$to.depth == this.depth && (t = this.findCloseLevel(this.$to)) && t.depth == this.depth) return -1;
		let { depth: n } = this.$to, r = this.$to.after(n);
		for (; n > 1 && r == this.$to.end(--n);) ++r;
		return r;
	}
	findCloseLevel(e) {
		scan: for (let t = Math.min(this.depth, e.depth); t >= 0; t--) {
			let { match: n, type: r } = this.frontier[t], i = t < e.depth && e.end(t + 1) == e.pos + (e.depth - (t + 1)), a = xc(e, t, r, n, i);
			if (a) {
				for (let n = t - 1; n >= 0; n--) {
					let { match: t, type: r } = this.frontier[n], i = xc(e, n, r, t, !0);
					if (!i || i.childCount) continue scan;
				}
				return {
					depth: t,
					fit: a,
					move: i ? e.doc.resolve(e.after(t + 1)) : e
				};
			}
		}
	}
	close(e) {
		let t = this.findCloseLevel(e);
		if (!t) return null;
		for (; this.depth > t.depth;) this.closeFrontierNode();
		t.fit.childCount && (this.placed = vc(this.placed, t.depth, t.fit)), e = t.move;
		for (let n = t.depth + 1; n <= e.depth; n++) {
			let t = e.node(n), r = t.type.contentMatch.fillBefore(t.content, !0, e.index(n));
			this.openFrontierNode(t.type, t.attrs, r);
		}
		return e;
	}
	openFrontierNode(e, t = null, n) {
		let r = this.frontier[this.depth];
		r.match = r.match.matchType(e), this.placed = vc(this.placed, this.depth, I.from(e.create(t, n))), this.frontier.push({
			type: e,
			match: e.contentMatch
		});
	}
	closeFrontierNode() {
		let e = this.frontier.pop().match.fillBefore(I.empty, !0);
		e.childCount && (this.placed = vc(this.placed, this.frontier.length, e));
	}
};
function _c(e, t, n) {
	return t == 0 ? e.cutByIndex(n, e.childCount) : e.replaceChild(0, e.firstChild.copy(_c(e.firstChild.content, t - 1, n)));
}
function vc(e, t, n) {
	return t == 0 ? e.append(n) : e.replaceChild(e.childCount - 1, e.lastChild.copy(vc(e.lastChild.content, t - 1, n)));
}
function yc(e, t) {
	for (let n = 0; n < t; n++) e = e.firstChild.content;
	return e;
}
function bc(e, t, n) {
	if (t <= 0) return e;
	let r = e.content;
	return t > 1 && (r = r.replaceChild(0, bc(r.firstChild, t - 1, r.childCount == 1 ? n - 1 : 0))), t > 0 && (r = e.type.contentMatch.fillBefore(r).append(r), n <= 0 && (r = r.append(e.type.contentMatch.matchFragment(r).fillBefore(I.empty, !0)))), e.copy(r);
}
function xc(e, t, n, r, i) {
	let a = e.node(t), o = i ? e.indexAfter(t) : e.index(t);
	if (o == a.childCount && !n.compatibleContent(a.type)) return null;
	let s = r.fillBefore(a.content, !0, o);
	return s && !Sc(n, a.content, o) ? s : null;
}
function Sc(e, t, n) {
	for (let r = n; r < t.childCount; r++) if (!e.allowsMarks(t.child(r).marks)) return !0;
	return !1;
}
function Cc(e) {
	return e.spec.defining || e.spec.definingForContent;
}
function wc(e, t) {
	for (let n = 0;; n++) {
		let r = t < 0 ? e.firstChild : e.lastChild;
		if (!r || r.isAtom) return n;
		e = r.content;
	}
}
function Tc(e, t, n, r) {
	if (!r.size) return e.deleteRange(t, n);
	let i = e.doc.resolve(t), a = e.doc.resolve(n);
	if (hc(i, a, r)) return e.step(new Vs(t, n, r));
	let o = kc(i, a);
	o[o.length - 1] == 0 && o.pop();
	let s = -(i.depth + 1);
	o.unshift(s);
	for (let e = i.depth, t = i.pos - 1; e > 0; e--, t--) {
		let n = i.node(e).type.spec;
		if (n.defining || n.definingAsContext || n.isolating) break;
		o.indexOf(e) > -1 ? s = e : i.before(e) == t && o.splice(1, 0, -e);
	}
	let c = o.indexOf(s), l = [], u = r.openStart;
	for (let e = r.content, t = 0;; t++) {
		let n = e.firstChild;
		if (l.push(n), t == r.openStart) break;
		e = n.content;
	}
	for (let e = u - 1; e >= 0; e--) {
		let t = l[e], n = Cc(t.type);
		if (n && !t.sameMarkup(i.node(Math.abs(s) - 1))) u = e;
		else if (n || !t.type.isTextblock) break;
	}
	for (let t = r.openStart; t >= 0; t--) {
		let s = (t + u + 1) % (r.openStart + 1), d = l[s];
		if (d) for (let t = 0; t < o.length; t++) {
			let l = o[(t + c) % o.length], u = !0;
			l < 0 && (u = !1, l = -l);
			let f = i.node(l - 1), p = i.index(l - 1);
			if (f.canReplaceWith(p, p, d.type, d.marks)) return e.replace(i.before(l), u ? a.after(l) : n, new R(Ec(r.content, 0, r.openStart, s), s, r.openEnd));
		}
	}
	let d = e.steps.length;
	for (let s = o.length - 1; s >= 0 && (e.replace(t, n, r), !(e.steps.length > d)); s--) {
		let e = o[s];
		e < 0 || (t = i.before(e), n = a.after(e));
	}
}
function Ec(e, t, n, r, i) {
	if (t < n) {
		let i = e.firstChild;
		e = e.replaceChild(0, i.copy(Ec(i.content, t + 1, n, r, i)));
	}
	if (t > r) {
		let t = i.contentMatchAt(0), n = t.fillBefore(e).append(e);
		e = n.append(t.matchFragment(n).fillBefore(I.empty, !0));
	}
	return e;
}
function Dc(e, t, n, r) {
	if (!r.isInline && t == n && e.doc.resolve(t).parent.content.size) {
		let i = fc(e.doc, t, r.type);
		i != null && (t = n = i);
	}
	e.replaceRange(t, n, new R(I.from(r), 0, 0));
}
function Oc(e, t, n) {
	let r = e.doc.resolve(t), i = e.doc.resolve(n);
	if (r.parent.isTextblock && i.parent.isTextblock && r.start() != i.start() && r.parentOffset == 0 && i.parentOffset == 0) {
		let a = r.sharedDepth(n), o = !1;
		for (let e = r.depth; e > a; e--) r.node(e).type.spec.isolating && (o = !0);
		for (let e = i.depth; e > a; e--) i.node(e).type.spec.isolating && (o = !0);
		if (!o) {
			for (let e = r.depth; e > 0 && t == r.start(e); e--) t = r.before(e);
			for (let e = i.depth; e > 0 && n == i.start(e); e--) n = i.before(e);
			r = e.doc.resolve(t), i = e.doc.resolve(n);
		}
	}
	let a = kc(r, i);
	for (let t = 0; t < a.length; t++) {
		let n = a[t], o = t == a.length - 1;
		if (o && n == 0 || r.node(n).type.contentMatch.validEnd) return e.delete(r.start(n), i.end(n));
		if (n > 0 && (o || r.node(n - 1).canReplace(r.index(n - 1), i.indexAfter(n - 1)))) return e.delete(r.before(n), i.after(n));
	}
	for (let a = 1; a <= r.depth && a <= i.depth; a++) if (t - r.start(a) == r.depth - a && n > r.end(a) && i.end(a) - n != i.depth - a && r.start(a - 1) == i.start(a - 1) && r.node(a - 1).canReplace(r.index(a - 1), i.index(a - 1))) return e.delete(r.before(a), n);
	e.delete(t, n);
}
function kc(e, t) {
	let n = [], r = Math.min(e.depth, t.depth);
	for (let i = r; i >= 0; i--) {
		let r = e.start(i);
		if (r < e.pos - (e.depth - i) || t.end(i) > t.pos + (t.depth - i) || e.node(i).type.spec.isolating || t.node(i).type.spec.isolating) break;
		(r == t.start(i) || i == e.depth && i == t.depth && e.parent.inlineContent && t.parent.inlineContent && i && t.start(i - 1) == r - 1) && n.push(i);
	}
	return n;
}
var Ac = class e extends z {
	constructor(e, t, n) {
		super(), this.pos = e, this.attr = t, this.value = n;
	}
	apply(e) {
		let t = e.nodeAt(this.pos);
		if (!t) return B.fail("No node at attribute step's position");
		let n = Object.create(null);
		for (let e in t.attrs) n[e] = t.attrs[e];
		n[this.attr] = this.value;
		let r = t.type.create(n, null, t.marks);
		return B.fromReplace(e, this.pos, this.pos + 1, new R(I.from(r), 0, +!t.isLeaf));
	}
	getMap() {
		return js.empty;
	}
	invert(t) {
		return new e(this.pos, this.attr, t.nodeAt(this.pos).attrs[this.attr]);
	}
	map(t) {
		let n = t.mapResult(this.pos, 1);
		return n.deletedAfter ? null : new e(n.pos, this.attr, this.value);
	}
	toJSON() {
		return {
			stepType: "attr",
			pos: this.pos,
			attr: this.attr,
			value: this.value
		};
	}
	static fromJSON(t, n) {
		if (typeof n.pos != "number" || typeof n.attr != "string") throw RangeError("Invalid input for AttrStep.fromJSON");
		return new e(n.pos, n.attr, n.value);
	}
};
z.jsonID("attr", Ac);
var jc = class e extends z {
	constructor(e, t) {
		super(), this.attr = e, this.value = t;
	}
	apply(e) {
		let t = Object.create(null);
		for (let n in e.attrs) t[n] = e.attrs[n];
		t[this.attr] = this.value;
		let n = e.type.create(t, e.content, e.marks);
		return B.ok(n);
	}
	getMap() {
		return js.empty;
	}
	invert(t) {
		return new e(this.attr, t.attrs[this.attr]);
	}
	map(e) {
		return this;
	}
	toJSON() {
		return {
			stepType: "docAttr",
			attr: this.attr,
			value: this.value
		};
	}
	static fromJSON(t, n) {
		if (typeof n.attr != "string") throw RangeError("Invalid input for DocAttrStep.fromJSON");
		return new e(n.attr, n.value);
	}
};
z.jsonID("docAttr", jc);
var Mc = class extends Error {};
Mc = function e(t) {
	let n = Error.call(this, t);
	return n.__proto__ = e.prototype, n;
}, Mc.prototype = Object.create(Error.prototype), Mc.prototype.constructor = Mc, Mc.prototype.name = "TransformError";
var Nc = class {
	constructor(e) {
		this.doc = e, this.steps = [], this.docs = [], this.mapping = new Ms();
	}
	get before() {
		return this.docs.length ? this.docs[0] : this.doc;
	}
	step(e) {
		let t = this.maybeStep(e);
		if (t.failed) throw new Mc(t.failed);
		return this;
	}
	maybeStep(e) {
		let t = e.apply(this.doc);
		return t.failed || this.addStep(e, t.doc), t;
	}
	get docChanged() {
		return this.steps.length > 0;
	}
	changedRange() {
		let e = 1e9, t = -1e9;
		for (let n = 0; n < this.mapping.maps.length; n++) {
			let r = this.mapping.maps[n];
			n && (e = r.map(e, 1), t = r.map(t, -1)), r.forEach((n, r, i, a) => {
				e = Math.min(e, i), t = Math.max(t, a);
			});
		}
		return e == 1e9 ? null : {
			from: e,
			to: t
		};
	}
	addStep(e, t) {
		this.docs.push(this.doc), this.steps.push(e), this.mapping.appendMap(e.getMap()), this.doc = t;
	}
	replace(e, t = e, n = R.empty) {
		let r = mc(this.doc, e, t, n);
		return r && this.step(r), this;
	}
	replaceWith(e, t, n) {
		return this.replace(e, t, new R(I.from(n), 0, 0));
	}
	delete(e, t) {
		return this.replace(e, t, R.empty);
	}
	insert(e, t) {
		return this.replaceWith(e, e, t);
	}
	replaceRange(e, t, n) {
		return Tc(this, e, t, n), this;
	}
	replaceRangeWith(e, t, n) {
		return Dc(this, e, t, n), this;
	}
	deleteRange(e, t) {
		return Oc(this, e, t), this;
	}
	lift(e, t) {
		return Ys(this, e, t), this;
	}
	join(e, t = 1) {
		return dc(this, e, t), this;
	}
	wrap(e, t) {
		return ec(this, e, t), this;
	}
	setBlockType(e, t = e, n, r = null) {
		return tc(this, e, t, n, r), this;
	}
	setNodeMarkup(e, t, n = null, r) {
		return ac(this, e, t, n, r), this;
	}
	setNodeAttribute(e, t, n) {
		return this.step(new Ac(e, t, n)), this;
	}
	setDocAttribute(e, t) {
		return this.step(new jc(e, t)), this;
	}
	addNodeMark(e, t) {
		return this.step(new zs(e, t)), this;
	}
	removeNodeMark(e, t) {
		let n = this.doc.nodeAt(e);
		if (!n) throw RangeError("No node at position " + e);
		if (t instanceof L) t.isInSet(n.marks) && this.step(new Bs(e, t));
		else {
			let r = n.marks, i, a = [];
			for (; i = t.isInSet(r);) a.push(new Bs(e, i)), r = i.removeFromSet(r);
			for (let e = a.length - 1; e >= 0; e--) this.step(a[e]);
		}
		return this;
	}
	split(e, t = 1, n) {
		return sc(this, e, t, n), this;
	}
	addMark(e, t, n) {
		return Ws(this, e, t, n), this;
	}
	removeMark(e, t, n) {
		return Gs(this, e, t, n), this;
	}
	clearIncompatible(e, t, n) {
		return Ks(this, e, t, n), this;
	}
}, Pc = Object.create(null), V = class {
	constructor(e, t, n) {
		this.$anchor = e, this.$head = t, this.ranges = n || [new Fc(e.min(t), e.max(t))];
	}
	get anchor() {
		return this.$anchor.pos;
	}
	get head() {
		return this.$head.pos;
	}
	get from() {
		return this.$from.pos;
	}
	get to() {
		return this.$to.pos;
	}
	get $from() {
		return this.ranges[0].$from;
	}
	get $to() {
		return this.ranges[0].$to;
	}
	get empty() {
		let e = this.ranges;
		for (let t = 0; t < e.length; t++) if (e[t].$from.pos != e[t].$to.pos) return !1;
		return !0;
	}
	content() {
		return this.$from.doc.slice(this.from, this.to, !0);
	}
	replace(e, t = R.empty) {
		let n = t.content.lastChild, r = null;
		for (let e = 0; e < t.openEnd; e++) r = n, n = n.lastChild;
		let i = e.steps.length, a = this.ranges;
		for (let o = 0; o < a.length; o++) {
			let { $from: s, $to: c } = a[o], l = e.mapping.slice(i);
			e.replaceRange(l.map(s.pos), l.map(c.pos), o ? R.empty : t), o == 0 && Uc(e, i, (n ? n.isInline : r && r.isTextblock) ? -1 : 1);
		}
	}
	replaceWith(e, t) {
		let n = e.steps.length, r = this.ranges;
		for (let i = 0; i < r.length; i++) {
			let { $from: a, $to: o } = r[i], s = e.mapping.slice(n), c = s.map(a.pos), l = s.map(o.pos);
			i ? e.deleteRange(c, l) : (e.replaceRangeWith(c, l, t), Uc(e, n, t.isInline ? -1 : 1));
		}
	}
	static findFrom(e, t, n = !1) {
		let r = e.parent.inlineContent ? new H(e) : Hc(e.node(0), e.parent, e.pos, e.index(), t, n);
		if (r) return r;
		for (let r = e.depth - 1; r >= 0; r--) {
			let i = t < 0 ? Hc(e.node(0), e.node(r), e.before(r + 1), e.index(r), t, n) : Hc(e.node(0), e.node(r), e.after(r + 1), e.index(r) + 1, t, n);
			if (i) return i;
		}
		return null;
	}
	static near(e, t = 1) {
		return this.findFrom(e, t) || this.findFrom(e, -t) || new Bc(e.node(0));
	}
	static atStart(e) {
		return Hc(e, e, 0, 0, 1) || new Bc(e);
	}
	static atEnd(e) {
		return Hc(e, e, e.content.size, e.childCount, -1) || new Bc(e);
	}
	static fromJSON(e, t) {
		if (!t || !t.type) throw RangeError("Invalid input for Selection.fromJSON");
		let n = Pc[t.type];
		if (!n) throw RangeError(`No selection type ${t.type} defined`);
		return n.fromJSON(e, t);
	}
	static jsonID(e, t) {
		if (e in Pc) throw RangeError("Duplicate use of selection JSON ID " + e);
		return Pc[e] = t, t.prototype.jsonID = e, t;
	}
	getBookmark() {
		return H.between(this.$anchor, this.$head).getBookmark();
	}
};
V.prototype.visible = !0;
var Fc = class {
	constructor(e, t) {
		this.$from = e, this.$to = t;
	}
}, Ic = !1;
function Lc(e) {
	!Ic && !e.parent.inlineContent && (Ic = !0, console.warn("TextSelection endpoint not pointing into a node with inline content (" + e.parent.type.name + ")"));
}
var H = class e extends V {
	constructor(e, t = e) {
		Lc(e), Lc(t), super(e, t);
	}
	get $cursor() {
		return this.$anchor.pos == this.$head.pos ? this.$head : null;
	}
	map(t, n) {
		let r = t.resolve(n.map(this.head));
		if (!r.parent.inlineContent) return V.near(r);
		let i = t.resolve(n.map(this.anchor));
		return new e(i.parent.inlineContent ? i : r, r);
	}
	replace(e, t = R.empty) {
		if (super.replace(e, t), t == R.empty) {
			let t = this.$from.marksAcross(this.$to);
			t && e.ensureMarks(t);
		}
	}
	eq(t) {
		return t instanceof e && t.anchor == this.anchor && t.head == this.head;
	}
	getBookmark() {
		return new Rc(this.anchor, this.head);
	}
	toJSON() {
		return {
			type: "text",
			anchor: this.anchor,
			head: this.head
		};
	}
	static fromJSON(t, n) {
		if (typeof n.anchor != "number" || typeof n.head != "number") throw RangeError("Invalid input for TextSelection.fromJSON");
		return new e(t.resolve(n.anchor), t.resolve(n.head));
	}
	static create(e, t, n = t) {
		let r = e.resolve(t);
		return new this(r, n == t ? r : e.resolve(n));
	}
	static between(t, n, r) {
		let i = t.pos - n.pos;
		if ((!r || i) && (r = i >= 0 ? 1 : -1), !n.parent.inlineContent) {
			let e = V.findFrom(n, r, !0) || V.findFrom(n, -r, !0);
			if (e) n = e.$head;
			else return V.near(n, r);
		}
		return t.parent.inlineContent || (i == 0 ? t = n : (t = (V.findFrom(t, -r, !0) || V.findFrom(t, r, !0)).$anchor, t.pos < n.pos != i < 0 && (t = n))), new e(t, n);
	}
};
V.jsonID("text", H);
var Rc = class e {
	constructor(e, t) {
		this.anchor = e, this.head = t;
	}
	map(t) {
		return new e(t.map(this.anchor), t.map(this.head));
	}
	resolve(e) {
		return H.between(e.resolve(this.anchor), e.resolve(this.head));
	}
}, U = class e extends V {
	constructor(e) {
		let t = e.nodeAfter, n = e.node(0).resolve(e.pos + t.nodeSize);
		super(e, n), this.node = t;
	}
	map(t, n) {
		let { deleted: r, pos: i } = n.mapResult(this.anchor), a = t.resolve(i);
		return r ? V.near(a) : new e(a);
	}
	content() {
		return new R(I.from(this.node), 0, 0);
	}
	eq(t) {
		return t instanceof e && t.anchor == this.anchor;
	}
	toJSON() {
		return {
			type: "node",
			anchor: this.anchor
		};
	}
	getBookmark() {
		return new zc(this.anchor);
	}
	static fromJSON(t, n) {
		if (typeof n.anchor != "number") throw RangeError("Invalid input for NodeSelection.fromJSON");
		return new e(t.resolve(n.anchor));
	}
	static create(t, n) {
		return new e(t.resolve(n));
	}
	static isSelectable(e) {
		return !e.isText && e.type.spec.selectable !== !1;
	}
};
U.prototype.visible = !1, V.jsonID("node", U);
var zc = class e {
	constructor(e) {
		this.anchor = e;
	}
	map(t) {
		let { deleted: n, pos: r } = t.mapResult(this.anchor);
		return n ? new Rc(r, r) : new e(r);
	}
	resolve(e) {
		let t = e.resolve(this.anchor), n = t.nodeAfter;
		return n && U.isSelectable(n) ? new U(t) : V.near(t);
	}
}, Bc = class e extends V {
	constructor(e) {
		super(e.resolve(0), e.resolve(e.content.size));
	}
	replace(e, t = R.empty) {
		if (t == R.empty) {
			e.delete(0, e.doc.content.size);
			let t = V.atStart(e.doc);
			t.eq(e.selection) || e.setSelection(t);
		} else super.replace(e, t);
	}
	toJSON() {
		return { type: "all" };
	}
	static fromJSON(t) {
		return new e(t);
	}
	map(t) {
		return new e(t);
	}
	eq(t) {
		return t instanceof e;
	}
	getBookmark() {
		return Vc;
	}
};
V.jsonID("all", Bc);
var Vc = {
	map() {
		return this;
	},
	resolve(e) {
		return new Bc(e);
	}
};
function Hc(e, t, n, r, i, a = !1) {
	if (t.inlineContent) return H.create(e, n);
	for (let o = r - (i > 0 ? 0 : 1); i > 0 ? o < t.childCount : o >= 0; o += i) {
		let r = t.child(o);
		if (!r.isAtom) {
			let t = Hc(e, r, n + i, i < 0 ? r.childCount : 0, i, a);
			if (t) return t;
		} else if (!a && U.isSelectable(r)) return U.create(e, n - (i < 0 ? r.nodeSize : 0));
		n += r.nodeSize * i;
	}
	return null;
}
function Uc(e, t, n) {
	let r = e.steps.length - 1;
	if (r < t) return;
	let i = e.steps[r];
	if (!(i instanceof Vs || i instanceof Hs)) return;
	let a = e.mapping.maps[r], o;
	a.forEach((e, t, n, r) => {
		o ??= r;
	}), e.setSelection(V.near(e.doc.resolve(o), n));
}
var Wc = 1, Gc = 2, Kc = 4, qc = class extends Nc {
	constructor(e) {
		super(e.doc), this.curSelectionFor = 0, this.updated = 0, this.meta = Object.create(null), this.time = Date.now(), this.curSelection = e.selection, this.storedMarks = e.storedMarks;
	}
	get selection() {
		return this.curSelectionFor < this.steps.length && (this.curSelection = this.curSelection.map(this.doc, this.mapping.slice(this.curSelectionFor)), this.curSelectionFor = this.steps.length), this.curSelection;
	}
	setSelection(e) {
		if (e.$from.doc != this.doc) throw RangeError("Selection passed to setSelection must point at the current document");
		return this.curSelection = e, this.curSelectionFor = this.steps.length, this.updated = (this.updated | Wc) & -3, this.storedMarks = null, this;
	}
	get selectionSet() {
		return (this.updated & Wc) > 0;
	}
	setStoredMarks(e) {
		return this.storedMarks = e, this.updated |= Gc, this;
	}
	ensureMarks(e) {
		return L.sameSet(this.storedMarks || this.selection.$from.marks(), e) || this.setStoredMarks(e), this;
	}
	addStoredMark(e) {
		return this.ensureMarks(e.addToSet(this.storedMarks || this.selection.$head.marks()));
	}
	removeStoredMark(e) {
		return this.ensureMarks(e.removeFromSet(this.storedMarks || this.selection.$head.marks()));
	}
	get storedMarksSet() {
		return (this.updated & Gc) > 0;
	}
	addStep(e, t) {
		super.addStep(e, t), this.updated &= -3, this.storedMarks = null;
	}
	setTime(e) {
		return this.time = e, this;
	}
	replaceSelection(e) {
		return this.selection.replace(this, e), this;
	}
	replaceSelectionWith(e, t = !0) {
		let n = this.selection;
		return t && (e = e.mark(this.storedMarks || (n.empty ? n.$from.marks() : n.$from.marksAcross(n.$to) || L.none))), n.replaceWith(this, e), this;
	}
	deleteSelection() {
		return this.selection.replace(this), this;
	}
	insertText(e, t, n) {
		let r = this.doc.type.schema;
		if (t == null) return e ? this.replaceSelectionWith(r.text(e), !0) : this.deleteSelection();
		{
			if (n ??= t, !e) return this.deleteRange(t, n);
			let i = this.storedMarks;
			if (!i) {
				let e = this.doc.resolve(t);
				i = n == t ? e.marks() : e.marksAcross(this.doc.resolve(n));
			}
			return this.replaceRangeWith(t, n, r.text(e, i)), !this.selection.empty && this.selection.to == t + e.length && this.setSelection(V.near(this.selection.$to)), this;
		}
	}
	setMeta(e, t) {
		return this.meta[typeof e == "string" ? e : e.key] = t, this;
	}
	getMeta(e) {
		return this.meta[typeof e == "string" ? e : e.key];
	}
	get isGeneric() {
		for (let e in this.meta) return !1;
		return !0;
	}
	scrollIntoView() {
		return this.updated |= Kc, this;
	}
	get scrolledIntoView() {
		return (this.updated & Kc) > 0;
	}
};
function Jc(e, t) {
	return !t || !e ? e : e.bind(t);
}
var Yc = class {
	constructor(e, t, n) {
		this.name = e, this.init = Jc(t.init, n), this.apply = Jc(t.apply, n);
	}
}, Xc = [
	new Yc("doc", {
		init(e) {
			return e.doc || e.schema.topNodeType.createAndFill();
		},
		apply(e) {
			return e.doc;
		}
	}),
	new Yc("selection", {
		init(e, t) {
			return e.selection || V.atStart(t.doc);
		},
		apply(e) {
			return e.selection;
		}
	}),
	new Yc("storedMarks", {
		init(e) {
			return e.storedMarks || null;
		},
		apply(e, t, n, r) {
			return r.selection.$cursor ? e.storedMarks : null;
		}
	}),
	new Yc("scrollToSelection", {
		init() {
			return 0;
		},
		apply(e, t) {
			return e.scrolledIntoView ? t + 1 : t;
		}
	})
], Zc = class {
	constructor(e, t) {
		this.schema = e, this.plugins = [], this.pluginsByKey = Object.create(null), this.fields = Xc.slice(), t && t.forEach((e) => {
			if (this.pluginsByKey[e.key]) throw RangeError("Adding different instances of a keyed plugin (" + e.key + ")");
			this.plugins.push(e), this.pluginsByKey[e.key] = e, e.spec.state && this.fields.push(new Yc(e.key, e.spec.state, e));
		});
	}
}, Qc = class e {
	constructor(e) {
		this.config = e;
	}
	get schema() {
		return this.config.schema;
	}
	get plugins() {
		return this.config.plugins;
	}
	apply(e) {
		return this.applyTransaction(e).state;
	}
	filterTransaction(e, t = -1) {
		for (let n = 0; n < this.config.plugins.length; n++) if (n != t) {
			let t = this.config.plugins[n];
			if (t.spec.filterTransaction && !t.spec.filterTransaction.call(t, e, this)) return !1;
		}
		return !0;
	}
	applyTransaction(e) {
		if (!this.filterTransaction(e)) return {
			state: this,
			transactions: []
		};
		let t = [e], n = this.applyInner(e), r = null;
		for (;;) {
			let i = !1;
			for (let a = 0; a < this.config.plugins.length; a++) {
				let o = this.config.plugins[a];
				if (o.spec.appendTransaction) {
					let s = r ? r[a].n : 0, c = r ? r[a].state : this, l = s < t.length && o.spec.appendTransaction.call(o, s ? t.slice(s) : t, c, n);
					if (l && n.filterTransaction(l, a)) {
						if (l.setMeta("appendedTransaction", e), !r) {
							r = [];
							for (let e = 0; e < this.config.plugins.length; e++) r.push(e < a ? {
								state: n,
								n: t.length
							} : {
								state: this,
								n: 0
							});
						}
						t.push(l), n = n.applyInner(l), i = !0;
					}
					r && (r[a] = {
						state: n,
						n: t.length
					});
				}
			}
			if (!i) return {
				state: n,
				transactions: t
			};
		}
	}
	applyInner(t) {
		if (!t.before.eq(this.doc)) throw RangeError("Applying a mismatched transaction");
		let n = new e(this.config), r = this.config.fields;
		for (let e = 0; e < r.length; e++) {
			let i = r[e];
			n[i.name] = i.apply(t, this[i.name], this, n);
		}
		return n;
	}
	get tr() {
		return new qc(this);
	}
	static create(t) {
		let n = new Zc(t.doc ? t.doc.type.schema : t.schema, t.plugins), r = new e(n);
		for (let e = 0; e < n.fields.length; e++) r[n.fields[e].name] = n.fields[e].init(t, r);
		return r;
	}
	reconfigure(t) {
		let n = new Zc(this.schema, t.plugins), r = n.fields, i = new e(n);
		for (let e = 0; e < r.length; e++) {
			let n = r[e].name;
			i[n] = this.hasOwnProperty(n) ? this[n] : r[e].init(t, i);
		}
		return i;
	}
	toJSON(e) {
		let t = {
			doc: this.doc.toJSON(),
			selection: this.selection.toJSON()
		};
		if (this.storedMarks && (t.storedMarks = this.storedMarks.map((e) => e.toJSON())), e && typeof e == "object") for (let n in e) {
			if (n == "doc" || n == "selection") throw RangeError("The JSON fields `doc` and `selection` are reserved");
			let r = e[n], i = r.spec.state;
			i && i.toJSON && (t[n] = i.toJSON.call(r, this[r.key]));
		}
		return t;
	}
	static fromJSON(t, n, r) {
		if (!n) throw RangeError("Invalid input for EditorState.fromJSON");
		if (!t.schema) throw RangeError("Required config field 'schema' missing");
		let i = new Zc(t.schema, t.plugins), a = new e(i);
		return i.fields.forEach((e) => {
			if (e.name == "doc") a.doc = wo.fromJSON(t.schema, n.doc);
			else if (e.name == "selection") a.selection = V.fromJSON(a.doc, n.selection);
			else if (e.name == "storedMarks") n.storedMarks && (a.storedMarks = n.storedMarks.map(t.schema.markFromJSON));
			else {
				if (r) for (let i in r) {
					let o = r[i], s = o.spec.state;
					if (o.key == e.name && s && s.fromJSON && Object.prototype.hasOwnProperty.call(n, i)) {
						a[e.name] = s.fromJSON.call(o, t, n[i], a);
						return;
					}
				}
				a[e.name] = e.init(t, a);
			}
		}), a;
	}
};
function $c(e, t, n) {
	for (let r in e) {
		let i = e[r];
		i instanceof Function ? i = i.bind(t) : r == "handleDOMEvents" && (i = $c(i, t, {})), n[r] = i;
	}
	return n;
}
var el = class {
	constructor(e) {
		this.spec = e, this.props = {}, e.props && $c(e.props, this, this.props), this.key = e.key ? e.key.key : nl("plugin");
	}
	getState(e) {
		return e[this.key];
	}
}, tl = Object.create(null);
function nl(e) {
	return e in tl ? e + "$" + ++tl[e] : (tl[e] = 0, e + "$");
}
var rl = class {
	constructor(e = "key") {
		this.key = nl(e);
	}
	get(e) {
		return e.config.pluginsByKey[this.key];
	}
	getState(e) {
		return e[this.key];
	}
}, il = (e, t) => !e.selection.empty && (t && t(e.tr.deleteSelection().scrollIntoView()), !0);
function al(e, t) {
	let { $cursor: n } = e.selection;
	return !n || (t ? !t.endOfTextblock("backward", e) : n.parentOffset > 0) ? null : n;
}
var ol = (e, t, n) => {
	let r = al(e, n);
	if (!r) return !1;
	let i = dl(r);
	if (!i) {
		let n = r.blockRange(), i = n && Js(n);
		return i != null && (t && t(e.tr.lift(n, i).scrollIntoView()), !0);
	}
	let a = i.nodeBefore;
	if (El(e, i, t, -1)) return !0;
	if (r.parent.content.size == 0 && (ll(a, "end") || U.isSelectable(a))) for (let n = r.depth;; n--) {
		let o = mc(e.doc, r.before(n), r.after(n), R.empty);
		if (o && o.slice.size < o.to - o.from) {
			if (t) {
				let n = e.tr.step(o);
				n.setSelection(ll(a, "end") ? V.findFrom(n.doc.resolve(n.mapping.map(i.pos, -1)), -1) : U.create(n.doc, i.pos - a.nodeSize)), t(n.scrollIntoView());
			}
			return !0;
		}
		if (n == 1 || r.node(n - 1).childCount > 1) break;
	}
	return a.isAtom && i.depth == r.depth - 1 ? (t && t(e.tr.delete(i.pos - a.nodeSize, i.pos).scrollIntoView()), !0) : !1;
}, sl = (e, t, n) => {
	let r = al(e, n);
	if (!r) return !1;
	let i = dl(r);
	return i ? cl(e, i, t) : !1;
};
function cl(e, t, n) {
	let r = t.nodeBefore, i = t.pos - 1;
	for (; !r.isTextblock; i--) {
		if (r.type.spec.isolating) return !1;
		let e = r.lastChild;
		if (!e) return !1;
		r = e;
	}
	let a = t.nodeAfter, o = t.pos + 1;
	for (; !a.isTextblock; o++) {
		if (a.type.spec.isolating) return !1;
		let e = a.firstChild;
		if (!e) return !1;
		a = e;
	}
	let s = mc(e.doc, i, o, R.empty);
	if (!s || s.from != i || s instanceof Vs && s.slice.size >= o - i) return !1;
	if (n) {
		let t = e.tr.step(s);
		t.setSelection(H.create(t.doc, i)), n(t.scrollIntoView());
	}
	return !0;
}
function ll(e, t, n = !1) {
	for (let r = e; r; r = t == "start" ? r.firstChild : r.lastChild) {
		if (r.isTextblock) return !0;
		if (n && r.childCount != 1) return !1;
	}
	return !1;
}
var ul = (e, t, n) => {
	let { $head: r, empty: i } = e.selection, a = r;
	if (!i) return !1;
	if (r.parent.isTextblock) {
		if (n ? !n.endOfTextblock("backward", e) : r.parentOffset > 0) return !1;
		a = dl(r);
	}
	let o = a && a.nodeBefore;
	return !o || !U.isSelectable(o) ? !1 : (t && t(e.tr.setSelection(U.create(e.doc, a.pos - o.nodeSize)).scrollIntoView()), !0);
};
function dl(e) {
	if (!e.parent.type.spec.isolating) for (let t = e.depth - 1; t >= 0; t--) {
		if (e.index(t) > 0) return e.doc.resolve(e.before(t + 1));
		if (e.node(t).type.spec.isolating) break;
	}
	return null;
}
function fl(e, t) {
	let { $cursor: n } = e.selection;
	return !n || (t ? !t.endOfTextblock("forward", e) : n.parentOffset < n.parent.content.size) ? null : n;
}
var pl = (e, t, n) => {
	let r = fl(e, n);
	if (!r) return !1;
	let i = hl(r);
	if (!i) return !1;
	let a = i.nodeAfter;
	if (El(e, i, t, 1)) return !0;
	if (r.parent.content.size == 0 && (ll(a, "start") || U.isSelectable(a))) {
		let n = mc(e.doc, r.before(), r.after(), R.empty);
		if (n && n.slice.size < n.to - n.from) {
			if (t) {
				let r = e.tr.step(n);
				r.setSelection(ll(a, "start") ? V.findFrom(r.doc.resolve(r.mapping.map(i.pos)), 1) : U.create(r.doc, r.mapping.map(i.pos))), t(r.scrollIntoView());
			}
			return !0;
		}
	}
	return a.isAtom && i.depth == r.depth - 1 ? (t && t(e.tr.delete(i.pos, i.pos + a.nodeSize).scrollIntoView()), !0) : !1;
}, ml = (e, t, n) => {
	let { $head: r, empty: i } = e.selection, a = r;
	if (!i) return !1;
	if (r.parent.isTextblock) {
		if (n ? !n.endOfTextblock("forward", e) : r.parentOffset < r.parent.content.size) return !1;
		a = hl(r);
	}
	let o = a && a.nodeAfter;
	return !o || !U.isSelectable(o) ? !1 : (t && t(e.tr.setSelection(U.create(e.doc, a.pos)).scrollIntoView()), !0);
};
function hl(e) {
	if (!e.parent.type.spec.isolating) for (let t = e.depth - 1; t >= 0; t--) {
		let n = e.node(t);
		if (e.index(t) + 1 < n.childCount) return e.doc.resolve(e.after(t + 1));
		if (n.type.spec.isolating) break;
	}
	return null;
}
var gl = (e, t) => {
	let { $from: n, $to: r } = e.selection, i = n.blockRange(r), a = i && Js(i);
	return a != null && (t && t(e.tr.lift(i, a).scrollIntoView()), !0);
}, _l = (e, t) => {
	let { $head: n, $anchor: r } = e.selection;
	return !n.parent.type.spec.code || !n.sameParent(r) ? !1 : (t && t(e.tr.insertText("\n").scrollIntoView()), !0);
};
function vl(e) {
	for (let t = 0; t < e.edgeCount; t++) {
		let { type: n } = e.edge(t);
		if (n.isTextblock && !n.hasRequiredAttrs()) return n;
	}
	return null;
}
var yl = (e, t) => {
	let { $head: n, $anchor: r } = e.selection;
	if (!n.parent.type.spec.code || !n.sameParent(r)) return !1;
	let i = n.node(-1), a = n.indexAfter(-1), o = vl(i.contentMatchAt(a));
	if (!o || !i.canReplaceWith(a, a, o)) return !1;
	if (t) {
		let r = n.after(), i = e.tr.replaceWith(r, r, o.createAndFill());
		i.setSelection(V.near(i.doc.resolve(r), 1)), t(i.scrollIntoView());
	}
	return !0;
}, bl = (e, t) => {
	let n = e.selection, { $from: r, $to: i } = n;
	if (n instanceof Bc || r.parent.inlineContent || i.parent.inlineContent) return !1;
	let a = vl(i.parent.contentMatchAt(i.indexAfter()));
	if (!a || !a.isTextblock) return !1;
	if (t) {
		let n = (!r.parentOffset && i.index() < i.parent.childCount ? r : i).pos, o = e.tr.insert(n, a.createAndFill());
		o.setSelection(H.create(o.doc, n + 1)), t(o.scrollIntoView());
	}
	return !0;
}, xl = (e, t) => {
	let { $cursor: n } = e.selection;
	if (!n || n.parent.content.size) return !1;
	if (n.depth > 1 && n.after() != n.end(-1)) {
		let r = n.before();
		if (oc(e.doc, r)) return t && t(e.tr.split(r).scrollIntoView()), !0;
	}
	let r = n.blockRange(), i = r && Js(r);
	return i != null && (t && t(e.tr.lift(r, i).scrollIntoView()), !0);
};
function Sl(e) {
	return (t, n) => {
		if (t.selection instanceof U && t.selection.node.isBlock) {
			let { $from: e } = t.selection;
			return !e.parentOffset || !oc(t.doc, e.pos) ? !1 : (n && n(t.tr.split(e.pos).scrollIntoView()), !0);
		}
		if (!t.selection.$from.depth) return !1;
		let r = t.tr;
		!t.selection.empty && (t.selection instanceof H || t.selection instanceof Bc) && r.deleteSelection();
		let { $from: i } = r.selection, a = r.steps.length, o = [], s, c, l = !1, u = !1;
		for (let t = i.depth;; t--) if (i.node(t).isBlock) {
			l = i.end(t) == i.pos + (i.depth - t), u = i.start(t) == i.pos - (i.depth - t), c = vl(i.node(t - 1).contentMatchAt(i.indexAfter(t - 1)));
			let n = e && e(i.parent, l, i);
			o.unshift(n || (l && c ? { type: c } : null)), s = t;
			break;
		} else {
			if (t == 1) return !1;
			o.unshift(null);
		}
		let d = i.pos, f = oc(r.doc, d, o.length, o);
		if (f ||= (o[0] = c ? { type: c } : null, oc(r.doc, d, o.length, o)), !f) return !1;
		if (r.split(d, o.length, o), !l && u && i.node(s).type != c) {
			let e = r.mapping.slice(a), t = e.map(i.before(s)), n = r.doc.resolve(t);
			c && i.node(s - 1).canReplaceWith(n.index(), n.index() + 1, c) && r.setNodeMarkup(e.map(i.before(s)), c);
		}
		return n && n(r.scrollIntoView()), !0;
	};
}
var Cl = Sl(), wl = (e, t) => (t && t(e.tr.setSelection(new Bc(e.doc))), !0);
function Tl(e, t, n) {
	let r = t.nodeBefore, i = t.nodeAfter, a = t.index();
	return !r || !i || !r.type.compatibleContent(i.type) ? !1 : !r.content.size && t.parent.canReplace(a - 1, a) ? (n && n(e.tr.delete(t.pos - r.nodeSize, t.pos).scrollIntoView()), !0) : !t.parent.canReplace(a, a + 1) || !(i.isTextblock || cc(e.doc, t.pos)) ? !1 : (n && n(e.tr.join(t.pos).scrollIntoView()), !0);
}
function El(e, t, n, r) {
	let i = t.nodeBefore, a = t.nodeAfter, o, s, c = i.type.spec.isolating || a.type.spec.isolating;
	if (!c && Tl(e, t, n)) return !0;
	let l = !c && t.parent.canReplace(t.index(), t.index() + 1);
	if (l && (o = (s = i.contentMatchAt(i.childCount)).findWrapping(a.type)) && s.matchType(o[0] || a.type).validEnd) {
		if (n) {
			let r = t.pos + a.nodeSize, s = I.empty;
			for (let e = o.length - 1; e >= 0; e--) s = I.from(o[e].create(null, s));
			s = I.from(i.copy(s));
			let c = e.tr.step(new Hs(t.pos - 1, r, t.pos, r, new R(s, 1, 0), o.length, !0)), l = c.doc.resolve(r + 2 * o.length);
			l.nodeAfter && l.nodeAfter.type == i.type && cc(c.doc, l.pos) && c.join(l.pos), n(c.scrollIntoView());
		}
		return !0;
	}
	let u = a.type.spec.isolating || r > 0 && c ? null : V.findFrom(t, 1), d = u && u.$from.blockRange(u.$to), f = d && Js(d);
	if (f != null && f >= t.depth) return n && n(e.tr.lift(d, f).scrollIntoView()), !0;
	if (l && ll(a, "start", !0) && ll(i, "end")) {
		let r = i, o = [];
		for (; o.push(r), !r.isTextblock;) r = r.lastChild;
		let s = a, c = 1;
		for (; !s.isTextblock; s = s.firstChild) c++;
		if (r.canReplace(r.childCount, r.childCount, s.content)) {
			if (n) {
				let r = I.empty;
				for (let e = o.length - 1; e >= 0; e--) r = I.from(o[e].copy(r));
				n(e.tr.step(new Hs(t.pos - o.length, t.pos + a.nodeSize, t.pos + c, t.pos + a.nodeSize - c, new R(r, o.length, 0), 0, !0)).scrollIntoView());
			}
			return !0;
		}
	}
	return !1;
}
function Dl(e) {
	return function(t, n) {
		let r = t.selection, i = e < 0 ? r.$from : r.$to, a = i.depth;
		for (; i.node(a).isInline;) {
			if (!a) return !1;
			a--;
		}
		return i.node(a).isTextblock ? (n && n(t.tr.setSelection(H.create(t.doc, e < 0 ? i.start(a) : i.end(a)))), !0) : !1;
	};
}
var Ol = Dl(-1), kl = Dl(1);
function Al(e, t = null) {
	return function(n, r) {
		let { $from: i, $to: a } = n.selection, o = i.blockRange(a), s = o && Xs(o, e, t);
		return s ? (r && r(n.tr.wrap(o, s).scrollIntoView()), !0) : !1;
	};
}
function jl(e, t = null) {
	return function(n, r) {
		let i = !1;
		for (let r = 0; r < n.selection.ranges.length && !i; r++) {
			let { $from: { pos: a }, $to: { pos: o } } = n.selection.ranges[r];
			n.doc.nodesBetween(a, o, (r, a) => {
				if (i) return !1;
				if (r.isTextblock && !r.hasMarkup(e, t)) {
					if (r.type == e) i = !0;
					else {
						let t = n.doc.resolve(a), r = t.index();
						i = t.parent.canReplaceWith(r, r + 1, e);
					}
				}
			});
		}
		if (!i) return !1;
		if (r) {
			let i = n.tr;
			for (let r = 0; r < n.selection.ranges.length; r++) {
				let { $from: { pos: a }, $to: { pos: o } } = n.selection.ranges[r];
				i.setBlockType(a, o, e, t);
			}
			r(i.scrollIntoView());
		}
		return !0;
	};
}
function Ml(e, t, n, r) {
	for (let i = 0; i < t.length; i++) {
		let { $from: a, $to: o } = t[i], s = a.depth == 0 && e.inlineContent && e.type.allowsMarkType(n);
		if (e.nodesBetween(a.pos, o.pos, (e, t) => {
			if (s || !r && e.isAtom && e.isInline && t >= a.pos && t + e.nodeSize <= o.pos) return !1;
			s = e.inlineContent && e.type.allowsMarkType(n);
		}), s) return !0;
	}
	return !1;
}
function Nl(e) {
	let t = [];
	for (let n = 0; n < e.length; n++) {
		let { $from: r, $to: i } = e[n];
		r.doc.nodesBetween(r.pos, i.pos, (e, n) => {
			if (e.isAtom && e.content.size && e.isInline && n >= r.pos && n + e.nodeSize <= i.pos) return n + 1 > r.pos && t.push(new Fc(r, r.doc.resolve(n + 1))), r = r.doc.resolve(n + 1 + e.content.size), !1;
		}), r.pos < i.pos && t.push(new Fc(r, i));
	}
	return t;
}
function Pl(e, t = null, n) {
	let r = (n && n.removeWhenPresent) !== !1, i = (n && n.enterInlineAtoms) !== !1, a = !(n && n.includeWhitespace);
	return function(n, o) {
		let { empty: s, $cursor: c, ranges: l } = n.selection;
		if (s && !c || !Ml(n.doc, l, e, i)) return !1;
		if (o) {
			if (c) e.isInSet(n.storedMarks || c.marks()) ? o(n.tr.removeStoredMark(e)) : o(n.tr.addStoredMark(e.create(t)));
			else {
				let s, c = n.tr;
				i || (l = Nl(l)), s = r ? !l.some((t) => n.doc.rangeHasMark(t.$from.pos, t.$to.pos, e)) : !l.every((t) => {
					let n = !1;
					return c.doc.nodesBetween(t.$from.pos, t.$to.pos, (r, i, a) => {
						if (n) return !1;
						n = !e.isInSet(r.marks) && !!a && a.type.allowsMarkType(e) && !(r.isText && /^\s*$/.test(r.textBetween(Math.max(0, t.$from.pos - i), Math.min(r.nodeSize, t.$to.pos - i))));
					}), !n;
				});
				for (let n = 0; n < l.length; n++) {
					let { $from: r, $to: i } = l[n];
					if (!s) c.removeMark(r.pos, i.pos, e);
					else {
						let n = r.pos, o = i.pos, s = r.nodeAfter, l = i.nodeBefore, u = a && s && s.isText ? /^\s*/.exec(s.text)[0].length : 0, d = a && l && l.isText ? /\s*$/.exec(l.text)[0].length : 0;
						n + u < o && (n += u, o -= d), c.addMark(n, o, e.create(t));
					}
				}
				o(c.scrollIntoView());
			}
		}
		return !0;
	};
}
function Fl(...e) {
	return function(t, n, r) {
		for (let i = 0; i < e.length; i++) if (e[i](t, n, r)) return !0;
		return !1;
	};
}
var Il = Fl(il, ol, ul), Ll = Fl(il, pl, ml), Rl = {
	Enter: Fl(_l, bl, xl, Cl),
	"Mod-Enter": yl,
	Backspace: Il,
	"Mod-Backspace": Il,
	"Shift-Backspace": Il,
	Delete: Ll,
	"Mod-Delete": Ll,
	"Mod-a": wl
}, zl = {
	"Ctrl-h": Rl.Backspace,
	"Alt-Backspace": Rl["Mod-Backspace"],
	"Ctrl-d": Rl.Delete,
	"Ctrl-Alt-Backspace": Rl["Mod-Delete"],
	"Alt-Delete": Rl["Mod-Delete"],
	"Alt-d": Rl["Mod-Delete"],
	"Ctrl-a": Ol,
	"Ctrl-e": kl
};
for (let e in Rl) zl[e] = Rl[e];
var Bl = (typeof navigator < "u" ? /Mac|iP(hone|[oa]d)/.test(navigator.platform) : typeof os < "u" && os.platform && os.platform() == "darwin") ? zl : Rl, Vl = class {
	constructor(e, t, n = {}) {
		this.match = e, this.match = e, this.handler = typeof t == "string" ? Hl(t) : t, this.undoable = n.undoable !== !1, this.inCode = n.inCode || !1, this.inCodeMark = n.inCodeMark !== !1;
	}
};
function Hl(e) {
	return function(t, n, r, i) {
		let a = e;
		if (n[1]) {
			let e = n[0].lastIndexOf(n[1]);
			a += n[0].slice(e + n[1].length), r += e;
			let t = r - i;
			t > 0 && (a = n[0].slice(e - t, e) + a, r = i);
		}
		return t.tr.insertText(a, r, i);
	};
}
var Ul = (e, t) => {
	let n = e.plugins;
	for (let r = 0; r < n.length; r++) {
		let i = n[r], a;
		if (i.spec.isInputRules && (a = i.getState(e))) {
			if (t) {
				let n = e.tr, r = a.transform;
				for (let e = r.steps.length - 1; e >= 0; e--) n.step(r.steps[e].invert(r.docs[e]));
				if (a.text) {
					let t = n.doc.resolve(a.from).marks();
					n.replaceWith(a.from, a.to, e.schema.text(a.text, t));
				} else n.delete(a.from, a.to);
				t(n);
			}
			return !0;
		}
	}
	return !1;
};
new Vl(/--$/, "—", { inCodeMark: !1 }), new Vl(/\.\.\.$/, "…", { inCodeMark: !1 }), new Vl(/(?:^|[\s\{\[\(\<'"\u2018\u201C])(")$/, "“", { inCodeMark: !1 }), new Vl(/"$/, "”", { inCodeMark: !1 }), new Vl(/(?:^|[\s\{\[\(\<'"\u2018\u201C])(')$/, "‘", { inCodeMark: !1 }), new Vl(/'$/, "’", { inCodeMark: !1 });
function Wl(e, t, n = null, r) {
	return new Vl(e, (e, i, a, o) => {
		let s = n instanceof Function ? n(i) : n, c = e.tr.delete(a, o), l = c.doc.resolve(a).blockRange(), u = l && Xs(l, t, s);
		if (!u) return null;
		c.wrap(l, u);
		let d = c.doc.resolve(a - 1).nodeBefore;
		return d && d.type == t && cc(c.doc, a - 1) && (!r || r(i, d)) && c.join(a - 1), c;
	});
}
function Gl(e, t, n = null) {
	return new Vl(e, (e, r, i, a) => {
		let o = e.doc.resolve(i), s = n instanceof Function ? n(r) : n;
		return o.node(-1).canReplaceWith(o.index(-1), o.indexAfter(-1), t) ? e.tr.delete(i, a).setBlockType(i, i, t, s) : null;
	});
}
// node_modules/.pnpm/@milkdown+prose@7.23.0/node_modules/@milkdown/prose/lib/index.js
var Kl = typeof navigator < "u" ? navigator : null, ql = typeof document < "u" ? document : null, Jl = Kl && Kl.userAgent || "", Yl = /Edge\/(\d+)/.exec(Jl), Xl = /MSIE \d/.exec(Jl), Zl = /Trident\/(?:[7-9]|\d{2,})\..*rv:(\d+)/.exec(Jl), Ql = !!(Xl || Zl || Yl), $l = Xl ? document.documentMode : Zl ? +Zl[1] : Yl ? +Yl[1] : 0, eu = !Ql && /gecko\/(\d+)/i.test(Jl), tu = eu && +(/Firefox\/(\d+)/.exec(Jl) || [0, 0])[1], nu = !Ql && /Chrome\/(\d+)/.exec(Jl), ru = !!nu, iu = nu ? +nu[1] : 0, au = !Ql && !!Kl && /Apple Computer/.test(Kl.vendor), ou = au && (/Mobile\/\w+/.test(Jl) || !!Kl && Kl.maxTouchPoints > 2), su = ou || (Kl ? /Mac/.test(Kl.platform) : !1), cu = /Android \d/.test(Jl), lu = !!ql && "webkitFontSmoothing" in ql.documentElement.style, uu = lu ? +(/\bAppleWebKit\/(\d+)/.exec(navigator.userAgent) || [0, 0])[1] : 0, du = /*#__PURE__*/ Object.freeze({
	__proto__: null,
	android: cu,
	chrome: ru,
	chrome_version: iu,
	gecko: eu,
	gecko_version: tu,
	ie: Ql,
	ie_version: $l,
	ios: ou,
	mac: su,
	safari: au,
	webkit: lu,
	webkit_version: uu
});
function fu(e, t, n, r, i, a) {
	if (e.composing) return !1;
	let o = e.state, s = o.doc.resolve(t);
	if (s.parent.type.spec.code) return !1;
	let c = s.parent.textBetween(Math.max(0, s.parentOffset - 500), s.parentOffset, void 0, "￼") + r;
	for (let s of i) {
		let i = s, l = i.match.exec(c), u = l && l[0] && i.handler(o, l, t - (l[0].length - r.length), n);
		if (u) return i.undoable !== !1 && u.setMeta(a, {
			transform: u,
			from: t,
			to: n,
			text: r
		}), e.dispatch(u), !0;
	}
	return !1;
}
var pu = new rl("MILKDOWN_CUSTOM_INPUTRULES");
function mu({ rules: e }) {
	let t = new el({
		key: pu,
		isInputRules: !0,
		state: {
			init() {
				return null;
			},
			apply(e, t) {
				return e.getMeta(this) || (e.selectionSet || e.docChanged ? null : t);
			}
		},
		props: {
			handleTextInput(n, r, i, a) {
				return fu(n, r, i, a, e, t);
			},
			handleDOMEvents: {
				compositionend: (n) => (setTimeout(() => {
					let { $cursor: r } = n.state.selection;
					r && fu(n, r.pos, r.pos, "", e, t);
				}), !1),
				keydown: (e, t) => !(cu && ru && t.key === "Enter") || e.composing ? !1 : e.someProp("handleKeyDown", (n) => n(e, t)) ? (t.preventDefault(), !0) : !1
			},
			handleKeyDown(n, r) {
				if (r.key !== "Enter") return !1;
				let { $cursor: i } = n.state.selection;
				return i ? fu(n, i.pos, i.pos, "\n", e, t) : !1;
			}
		}
	});
	return t;
}
function hu(e) {
	return e.some((e) => e.type.spec.code);
}
function gu(e, t, n) {
	if (t >= n) return !1;
	let r = !1;
	return e.doc.nodesBetween(t, n, (e) => (e.isInline && hu(e.marks) && (r = !0), !r)), r;
}
function _u(e, t) {
	return hu(e.storedMarks ?? e.doc.resolve(t).marks());
}
function vu(e, t, n = {}) {
	return new Vl(e, (e, r, i, a) => {
		var o;
		let { tr: s } = e, c = r[r.length - 1], l = r[0], u = [], d, f = {
			group: c,
			fullMatch: l,
			start: i,
			end: a
		}, p = n.updateCaptured?.call(n, f);
		if (Object.assign(f, p), {group: c, fullMatch: l, start: i, end: a} = f, l === null || c?.trim() === "") return null;
		if (c) {
			let f = l.search(/\S/), p = i + l.indexOf(c), m = p + c.length, h = gu(e, i + f, p), g = m < a && gu(e, m, a);
			if (h || g || _u(e, a)) return null;
			u = s.storedMarks ?? [], m < a && s.delete(m, a), p > i && s.delete(i + f, p), d = i + f + c.length;
			let _ = n.getAttr?.call(n, r);
			s.addMark(i, d, t.create(_)), s.setStoredMarks(u), (o = n.beforeDispatch) == null || o.call(n, {
				match: r,
				start: i,
				end: a,
				tr: s
			});
		}
		return s;
	});
}
function yu(e, t, n = {}) {
	return new Vl(e, (e, r, i, a) => {
		var o;
		let { tr: s } = e, c = r[1], l = r[0], u = {
			group: c,
			fullMatch: l,
			start: i,
			end: a
		}, d = n.updateCaptured?.call(n, u);
		if (Object.assign(u, d), {group: c, fullMatch: l, start: i, end: a} = u, l === null || !c || c.trim() === "") return null;
		let f = n.getAttr?.call(n, r), p = t.createAndFill(f);
		return p && (s.replaceRangeWith(t.isBlock ? s.doc.resolve(i).before() : i, a, p), (o = n.beforeDispatch) == null || o.call(n, {
			match: [l, c],
			start: i,
			end: a,
			tr: s
		})), s;
	});
}
var bu = Object.defineProperty, xu = Object.defineProperties, Su = Object.getOwnPropertyDescriptors, Cu = Object.getOwnPropertySymbols, wu = Object.prototype.hasOwnProperty, Tu = Object.prototype.propertyIsEnumerable, Eu = (e, t, n) => t in e ? bu(e, t, {
	enumerable: !0,
	configurable: !0,
	writable: !0,
	value: n
}) : e[t] = n, Du = (e, t) => {
	for (var n in t ||= {}) wu.call(t, n) && Eu(e, n, t[n]);
	if (Cu) for (var n of Cu(t)) Tu.call(t, n) && Eu(e, n, t[n]);
	return e;
}, Ou = (e, t) => xu(e, Su(t));
function ku(e = 0, t = 0, n = 0) {
	return Math.min(Math.max(e, t), n);
}
function Au(e, t, n) {
	let r = e.state.doc.content.size, i = ku(t, 0, r), a = ku(n, 0, r), o = e.coordsAtPos(i), s = e.coordsAtPos(a, -1), c = Math.min(o.top, s.top), l = Math.max(o.bottom, s.bottom), u = Math.min(o.left, s.left), d = Math.max(o.right, s.right), f = {
		top: c,
		bottom: l,
		left: u,
		right: d,
		width: d - u,
		height: l - c,
		x: u,
		y: c
	};
	return Ou(Du({}, f), { toJSON: () => f });
}
function ju(e) {
	return Object.assign(Object.create(e), e).setTime(Date.now());
}
function Mu(e, t) {
	return Array.isArray(e) && e.includes(t.type) || t.type === e;
}
function Nu(e) {
	if (e.content.childCount === 1) {
		let t = e.content.firstChild;
		if (t?.type.name === "text" && t.marks.length === 0) return t;
		if (t?.type.name === "paragraph" && t.childCount === 1) {
			let e = t.firstChild;
			if (e?.type.name === "text" && e.marks.length === 0) return e;
		}
	}
	return !1;
}
function Pu(e) {
	return (t) => {
		for (let n = t.depth; n > 0; --n) {
			let r = t.node(n);
			if (e(r)) return {
				from: t.before(n),
				to: t.after(n),
				node: r
			};
		}
	};
}
function Fu(e, t) {
	return Pu((e) => e.type === t)(e);
}
function Iu(e, t) {
	let n = t.nodes[e];
	if (!n) throw _("node", e);
	return n;
}
function Lu(e) {
	return (t) => {
		for (let n = t.depth; n > 0; n--) {
			let r = t.node(n);
			if (e(r)) return {
				pos: t.before(n),
				start: t.start(n),
				depth: n,
				node: r
			};
		}
	};
}
function Ru(e, t) {
	if (!(e instanceof U)) return;
	let { node: n, $from: r } = e;
	if (Mu(t, n)) return {
		node: n,
		pos: r.pos,
		start: r.start(r.depth),
		depth: r.depth
	};
}
var zu = (e, t) => {
	let { selection: n, doc: r } = e;
	if (n instanceof U) return {
		hasNode: n.node.type === t,
		pos: n.from,
		target: n.node
	};
	let { from: i, to: a } = n, o = !1, s = -1, c = null;
	return r.nodesBetween(i, a, (e, n) => c ? !1 : e.type !== t || (o = !0, s = n, c = e, !1)), {
		hasNode: o,
		pos: s,
		target: c
	};
}, Bu = typeof navigator < "u" && /Mac|iP(hone|[oa]d)/.test(navigator.platform), Vu = typeof navigator < "u" && /Win/.test(navigator.platform);
function Hu(e) {
	let t = e.split(/-(?!$)/), n = t[t.length - 1];
	n == "Space" && (n = " ");
	let r, i, a, o;
	for (let e = 0; e < t.length - 1; e++) {
		let n = t[e];
		if (/^(cmd|meta|m)$/i.test(n)) o = !0;
		else if (/^a(lt)?$/i.test(n)) r = !0;
		else if (/^(c|ctrl|control)$/i.test(n)) i = !0;
		else if (/^s(hift)?$/i.test(n)) a = !0;
		else if (/^mod$/i.test(n)) Bu ? o = !0 : i = !0;
		else throw Error("Unrecognized modifier name: " + n);
	}
	return r && (n = "Alt-" + n), i && (n = "Ctrl-" + n), o && (n = "Meta-" + n), a && (n = "Shift-" + n), n;
}
function Uu(e) {
	let t = Object.create(null);
	for (let n in e) t[Hu(n)] = e[n];
	return t;
}
function Wu(e, t, n = !0) {
	return t.altKey && (e = "Alt-" + e), t.ctrlKey && (e = "Ctrl-" + e), t.metaKey && (e = "Meta-" + e), n && t.shiftKey && (e = "Shift-" + e), e;
}
function Gu(e) {
	return new el({ props: { handleKeyDown: Ku(e) } });
}
function Ku(e) {
	let t = Uu(e);
	return function(e, n) {
		let a = r(n), o, s = t[Wu(a, n)];
		if (s && s(e.state, e.dispatch, e)) return !0;
		if (a.length == 1 && a != " ") {
			if (n.shiftKey) {
				let r = t[Wu(a, n, !1)];
				if (r && r(e.state, e.dispatch, e)) return !0;
			}
			if ((n.altKey || n.metaKey || n.ctrlKey) && !(Vu && n.ctrlKey && n.altKey) && (o = i[n.keyCode]) && o != a) {
				let r = t[Wu(o, n)];
				if (r && r(e.state, e.dispatch, e)) return !0;
			}
		}
		return !1;
	};
}
// node_modules/.pnpm/@milkdown+transformer@7.23.0/node_modules/@milkdown/transformer/lib/index.js
var qu = class {}, Ju = class {
	constructor() {
		this.elements = [], this.size = () => this.elements.length, this.top = () => this.elements.at(-1), this.push = (e) => {
			this.top()?.push(e);
		}, this.open = (e) => {
			this.elements.push(e);
		}, this.close = () => {
			let e = this.elements.pop();
			if (!e) throw m();
			return e;
		};
	}
}, Yu = class e extends qu {
	constructor(e, t, n) {
		super(), this.type = e, this.content = t, this.attrs = n;
	}
	push(e, ...t) {
		this.content.push(e, ...t);
	}
	pop() {
		return this.content.pop();
	}
	static create(t, n, r) {
		return new e(t, n, r);
	}
}, Xu = class extends Ju {
	#e;
	static {
		this.create = (e, t) => {
			let n = new this(e);
			return (e) => (n.run(t, e), n.toDoc());
		};
	}
	constructor(e) {
		super(), this.#e = L.none, this.#t = (e) => e.isText, this.#n = (e, t) => {
			if (this.#t(e) && this.#t(t) && L.sameSet(e.marks, t.marks)) return this.schema.text(e.text + t.text, e.marks);
		}, this.#r = (e) => {
			let t = Object.values({
				...this.schema.nodes,
				...this.schema.marks
			}).find((t) => t.spec.parseMarkdown.match(e));
			if (!t) throw h(e);
			return t;
		}, this.#i = (e) => {
			let t = this.#r(e);
			t.spec.parseMarkdown.runner(this, e, t);
		}, this.injectRoot = (e, t, n) => (this.openNode(t, n), this.next(e.children), this), this.openNode = (e, t) => (this.open(Yu.create(e, [], t)), this), this.#a = () => {
			this.#e = L.none;
			let e = this.close();
			return this.#o(e.type, e.attrs, e.content);
		}, this.closeNode = () => {
			try {
				this.#a();
			} catch (e) {
				console.error(e);
			}
			return this;
		}, this.#o = (e, t, n) => {
			let r = e.createAndFill(t, n, this.#e);
			if (!r) throw p(e, t, n);
			return this.push(r), r;
		}, this.addNode = (e, t, n) => {
			try {
				this.#o(e, t, n);
			} catch (e) {
				console.error(e);
			}
			return this;
		}, this.openMark = (e, t) => {
			let n = e.create(t);
			return this.#e = n.addToSet(this.#e), this;
		}, this.closeMark = (e) => (this.#e = e.removeFromSet(this.#e), this), this.addText = (e) => {
			try {
				let t = this.top();
				if (!t) throw m();
				let n = t.pop(), r = this.schema.text(e, this.#e);
				if (!n) return t.push(r), this;
				let i = this.#n(n, r);
				return i ? (t.push(i), this) : (t.push(n, r), this);
			} catch (e) {
				return console.error(e), this;
			}
		}, this.build = () => {
			let e;
			do
				e = this.#a();
			while (this.size());
			return e;
		}, this.next = (e = []) => ([e].flat().forEach((e) => this.#i(e)), this), this.toDoc = () => this.build(), this.run = (e, t) => {
			let n = e.runSync(e.parse(t), t);
			return this.next(n), this;
		}, this.schema = e;
	}
	#t;
	#n;
	#r;
	#i;
	#a;
	#o;
}, Zu = class e extends qu {
	constructor(e, t, n, r = {}) {
		super(), this.type = e, this.children = t, this.value = n, this.props = r, this.push = (e, ...t) => {
			this.children ||= [], this.children.push(e, ...t);
		}, this.pop = () => this.children?.pop();
	}
	static {
		this.create = (t, n, r, i = {}) => new e(t, n, r, i);
	}
}, Qu = (e) => Object.prototype.hasOwnProperty.call(e, "size"), $u = class extends Ju {
	#e;
	static {
		this.create = (e, t) => {
			let n = new this(e);
			return (e) => (n.run(e), n.toString(t));
		};
	}
	constructor(e) {
		super(), this.#e = [], this.#t = (e) => {
			let t = Object.values({
				...this.schema.nodes,
				...this.schema.marks
			}).find((t) => t.spec.toMarkdown.match(e));
			if (!t) throw g(e.type);
			return t;
		}, this.#n = (e) => this.#t(e).spec.toMarkdown.runner(this, e), this.#r = (e, t) => this.#t(e).spec.toMarkdown.runner(this, e, t), this.#i = (e) => {
			let t = (e) => e.type.spec.priority ?? 50, n = [...e].sort((e, n) => t(e) - t(n)), r = [];
			return this.#e.forEach(({ mark: e }) => {
				let t = n.findIndex((t) => t.eq(e));
				t >= 0 && r.push(...n.splice(t, 1));
			}), r.concat(n);
		}, this.#a = (e) => {
			let t = e?.marks, n = 0;
			for (; n < this.#e.length;) {
				let { mark: e, spanning: r } = this.#e[n];
				if (r && t?.some((t) => t.eq(e))) n++;
				else break;
			}
			for (let e = this.#e.length - 1; e >= n; e--) this.#p(this.#e[e].mark);
		}, this.#o = (e, t) => {
			this.#i(e.marks).every((t) => !this.#r(t, e)) && this.#n(e), this.#a(t);
		}, this.#s = (e) => {
			let { children: t } = e;
			return t && (e.children = t.reduce((e, t, n) => {
				if (n === 0) return [t];
				let r = e.at(-1);
				if (r && r.isMark && t.isMark) {
					let { children: n, ...i } = t, { children: a, ...o } = r;
					if (t.type === r.type && n && a && JSON.stringify(i) === JSON.stringify(o)) {
						let t = {
							...o,
							children: [...a, ...n]
						};
						return e.slice(0, -1).concat(this.#s(t));
					}
				}
				return e.concat(t);
			}, [])), e;
		}, this.#c = (e) => {
			let t = {
				...e.props,
				type: e.type
			};
			return e.children && (t.children = e.children), e.value && (t.value = e.value), t;
		}, this.openNode = (e, t, n) => (this.open(Zu.create(e, void 0, t, n)), this), this.#l = (e, t) => {
			let n = "", r = "", i = e.children;
			if (i) {
				let e = i[0], t = i.at(-1);
				if (t && t.type === "text" && t.value.endsWith(" ")) {
					let e = t.value, n = e.trimEnd();
					r = e.slice(n.length), t.value = n;
				}
				if (e && e.type === "text" && e.value.startsWith(" ")) {
					let t = e.value, r = t.trimStart();
					n = t.slice(0, t.length - r.length), e.value = r;
				}
			}
			n.length && this.#d("text", void 0, n);
			let a = t();
			return r.length && this.#d("text", void 0, r), a;
		}, this.#u = (e = !1) => {
			let t = this.close(), n = () => this.#d(t.type, t.children, t.value, t.props);
			return e ? this.#l(t, n) : n();
		}, this.closeNode = () => (this.#u(), this), this.#d = (e, t, n, r) => {
			let i = Zu.create(e, t, n, r), a = this.#s(this.#c(i));
			return this.push(a), a;
		}, this.addNode = (e, t, n, r) => (this.#d(e, t, n, r), this), this.#f = (e, t, n, r) => this.#e.some((t) => t.mark.eq(e)) ? this : (this.#e.push({
			mark: e,
			spanning: n == null
		}), this.openNode(t, n, {
			...r,
			isMark: !0
		})), this.#p = (e) => {
			let t = -1;
			for (let n = this.#e.length - 1; n >= 0; n--) if (this.#e[n].mark.eq(e)) {
				t = n;
				break;
			}
			t < 0 || (this.#e.splice(t, 1), this.#u(!0));
		}, this.withMark = (e, t, n, r) => (this.#f(e, t, n, r), this), this.closeMark = (e) => (this.#p(e), this), this.build = () => {
			let e = null;
			do
				e = this.#u();
			while (this.size());
			return e.children || (e.children = []), e;
		}, this.next = (e) => Qu(e) ? (e.forEach((t, n, r) => {
			this.#o(t, e.maybeChild(r + 1) ?? void 0);
		}), this) : (this.#o(e), this), this.toString = (e) => e.stringify(this.build()), this.run = (e) => (this.#e = [], this.next(e), this), this.schema = e;
	}
	#t;
	#n;
	#r;
	#i;
	#a;
	#o;
	#s;
	#c;
	#l;
	#u;
	#d;
	#f;
	#p;
}, W = function(e) {
	for (var t = 0;; t++) if (e = e.previousSibling, !e) return t;
}, ed = function(e) {
	let t = e.assignedSlot || e.parentNode;
	return t && t.nodeType == 11 ? t.host : t;
}, td = null, nd = function(e, t, n) {
	let r = td ||= document.createRange();
	return r.setEnd(e, n ?? e.nodeValue.length), r.setStart(e, t || 0), r;
}, rd = function() {
	td = null;
}, id = function(e, t, n, r) {
	return n && (od(e, t, n, r, -1) || od(e, t, n, r, 1));
}, ad = /^(img|br|input|textarea|hr)$/i;
function od(e, t, n, r, i) {
	for (;;) {
		if (e == n && t == r) return !0;
		if (t == (i < 0 ? 0 : G(e))) {
			let n = e.parentNode;
			if (!n || !K(n) || dd(e) || ad.test(e.nodeName) || e.contentEditable == "false") return !1;
			t = W(e) + (i < 0 ? 0 : 1), e = n;
		} else if (K(e)) {
			let n = e.childNodes[t + (i < 0 ? -1 : 0)];
			if (K(n) && n.contentEditable == "false") {
				if (n.pmViewDesc?.ignoreForSelection) t += i;
				else return !1;
			} else e = n, t = i < 0 ? G(e) : 0;
		} else return !1;
	}
}
function G(e) {
	return e.nodeType == 3 ? e.nodeValue.length : e.childNodes.length;
}
function K(e) {
	return e.nodeType == 1;
}
function q(e) {
	return e.nodeType == 3;
}
function sd(e, t) {
	return t ? e.contains(K(t) ? t : t.parentNode) : !1;
}
function cd(e, t) {
	for (;;) {
		if (q(e) && t) return e;
		if (K(e) && t > 0) {
			if (e.contentEditable == "false") return null;
			e = e.childNodes[t - 1], t = G(e);
		} else if (e.parentNode && !dd(e)) t = W(e), e = e.parentNode;
		else return null;
	}
}
function ld(e, t) {
	for (;;) {
		if (q(e) && t < e.nodeValue.length) return e;
		if (K(e) && t < e.childNodes.length) {
			if (e.contentEditable == "false") return null;
			e = e.childNodes[t], t = 0;
		} else if (e.parentNode && !dd(e)) t = W(e) + 1, e = e.parentNode;
		else return null;
	}
}
function ud(e, t, n) {
	for (let r = t == 0, i = t == G(e); r || i;) {
		if (e == n) return !0;
		let t = W(e);
		if (e = e.parentNode, !e) return !1;
		r &&= t == 0, i &&= t == G(e);
	}
}
function dd(e) {
	let t;
	for (let n = e; n && !(t = n.pmViewDesc); n = n.parentNode);
	return t && t.node && t.node.isBlock && (t.dom == e || t.contentDOM == e);
}
var fd = function(e) {
	return e.focusNode && id(e.focusNode, e.focusOffset, e.anchorNode, e.anchorOffset);
};
function pd(e, t) {
	let n = document.createEvent("Event");
	return n.initEvent("keydown", !0, !0), n.keyCode = e, n.key = n.code = t, n;
}
function md(e) {
	let t = e.activeElement;
	for (; t && t.shadowRoot;) t = t.shadowRoot.activeElement;
	return t;
}
function hd(e, t, n) {
	if (e.caretPositionFromPoint) try {
		let r = e.caretPositionFromPoint(t, n);
		if (r) return {
			node: r.offsetNode,
			offset: Math.min(G(r.offsetNode), r.offset)
		};
	} catch {}
	if (e.caretRangeFromPoint) {
		let r = e.caretRangeFromPoint(t, n);
		if (r) return {
			node: r.startContainer,
			offset: Math.min(G(r.startContainer), r.startOffset)
		};
	}
}
var gd = typeof navigator < "u" ? navigator : null, _d = typeof document < "u" ? document : null, vd = gd && gd.userAgent || "", yd = /Edge\/(\d+)/.exec(vd), bd = /MSIE \d/.exec(vd), xd = /Trident\/(?:[7-9]|\d{2,})\..*rv:(\d+)/.exec(vd), J = !!(bd || xd || yd), Sd = bd ? document.documentMode : xd ? +xd[1] : yd ? +yd[1] : 0, Cd = !J && /gecko\/(\d+)/i.test(vd);
Cd && +(/Firefox\/(\d+)/.exec(vd) || [0, 0])[1];
var wd = !J && /Chrome\/(\d+)/.exec(vd), Y = !!wd, Td = wd ? +wd[1] : 0, X = !J && !!gd && /Apple Computer/.test(gd.vendor), Ed = X && (/Mobile\/\w+/.test(vd) || !!gd && gd.maxTouchPoints > 2), Dd = Ed || (gd ? /Mac/.test(gd.platform) : !1), Od = gd ? /Win/.test(gd.platform) : !1, kd = /Android \d/.test(vd), Ad = !!_d && "webkitFontSmoothing" in _d.documentElement.style, jd = Ad ? +(/\bAppleWebKit\/(\d+)/.exec(navigator.userAgent) || [0, 0])[1] : 0;
function Md(e) {
	let t = e.defaultView && e.defaultView.visualViewport;
	return t ? {
		left: 0,
		right: t.width,
		top: 0,
		bottom: t.height
	} : {
		left: 0,
		right: e.documentElement.clientWidth,
		top: 0,
		bottom: e.documentElement.clientHeight
	};
}
function Nd(e, t) {
	return typeof e == "number" ? e : e[t];
}
function Pd(e) {
	let t = e.getBoundingClientRect(), n = t.width / e.offsetWidth || 1, r = t.height / e.offsetHeight || 1;
	return {
		left: t.left,
		right: t.left + e.clientWidth * n,
		top: t.top,
		bottom: t.top + e.clientHeight * r
	};
}
function Fd(e, t, n) {
	if (!Xd(t) && t.left == 0) return;
	let r = e.someProp("scrollThreshold") || 0, i = e.someProp("scrollMargin") || 5, a = e.dom.ownerDocument;
	for (let o = n || e.dom; o;) {
		if (!K(o)) {
			o = ed(o);
			continue;
		}
		let e = o == a.body, n = e ? Md(a) : Pd(o), s = 0, c = 0;
		if (t.top < n.top + Nd(r, "top") ? c = -(n.top - t.top + Nd(i, "top")) : t.bottom > n.bottom - Nd(r, "bottom") && (c = t.bottom - t.top > n.bottom - n.top ? t.top + Nd(i, "top") - n.top : t.bottom - n.bottom + Nd(i, "bottom")), t.left < n.left + Nd(r, "left") ? s = -(n.left - t.left + Nd(i, "left")) : t.right > n.right - Nd(r, "right") && (s = t.right - n.right + Nd(i, "right")), s || c) {
			if (e) a.defaultView.scrollBy(s, c);
			else {
				let e = o.scrollLeft, n = o.scrollTop;
				c && (o.scrollTop += c), s && (o.scrollLeft += s);
				let r = o.scrollLeft - e, i = o.scrollTop - n;
				t = {
					left: t.left - r,
					top: t.top - i,
					right: t.right - r,
					bottom: t.bottom - i
				};
			}
		}
		let l = e ? "fixed" : getComputedStyle(o).position;
		if (/^(fixed|sticky)$/.test(l)) break;
		o = l == "absolute" ? o.offsetParent : ed(o);
	}
}
function Id(e) {
	let t = e.dom.getBoundingClientRect(), n = Math.max(0, t.top), r, i;
	for (let a = (t.left + t.right) / 2, o = n + 1; o < Math.min(innerHeight, t.bottom); o += 5) {
		let t = e.root.elementFromPoint(a, o);
		if (!t || t == e.dom || !e.dom.contains(t)) continue;
		let s = t.getBoundingClientRect();
		if (s.top >= n - 20) {
			r = t, i = s.top;
			break;
		}
	}
	return {
		refDOM: r,
		refTop: i,
		stack: Ld(e.dom)
	};
}
function Ld(e) {
	let t = [], n = e.ownerDocument;
	for (let r = e; r && (t.push({
		dom: r,
		top: r.scrollTop,
		left: r.scrollLeft
	}), e != n); r = ed(r));
	return t;
}
function Rd({ refDOM: e, refTop: t, stack: n }) {
	let r = e ? e.getBoundingClientRect().top : 0;
	zd(n, r == 0 ? 0 : r - t);
}
function zd(e, t) {
	for (let n = 0; n < e.length; n++) {
		let { dom: r, top: i, left: a } = e[n];
		r.scrollTop != i + t && (r.scrollTop = i + t), r.scrollLeft != a && (r.scrollLeft = a);
	}
}
var Bd = null;
function Vd(e) {
	if (e.setActive) return e.setActive();
	if (Bd) return e.focus(Bd);
	let t = Ld(e);
	e.focus(Bd == null ? { get preventScroll() {
		return Bd = { preventScroll: !0 }, !0;
	} } : void 0), Bd || (Bd = !1, zd(t, 0));
}
function Hd(e, t) {
	let n, r = 2e8, i, a = 0, o = t.top, s = t.top, c, l;
	for (let u = e.firstChild, d = 0; u; u = u.nextSibling, d++) {
		let e;
		if (K(u)) e = u.getClientRects();
		else if (q(u)) e = nd(u).getClientRects();
		else continue;
		for (let f = 0; f < e.length; f++) {
			let p = e[f];
			if (p.top <= o && p.bottom >= s) {
				o = Math.max(p.bottom, o), s = Math.min(p.top, s);
				let e = p.left > t.left ? p.left - t.left : p.right < t.left ? t.left - p.right : 0;
				if (e < r) {
					n = u, r = e, i = e && q(n) ? {
						left: p.right < t.left ? p.right : p.left,
						top: t.top
					} : t, K(u) && e && (a = d + +(t.left >= (p.left + p.right) / 2));
					continue;
				}
			} else p.top > t.top && !c && p.left <= t.left && p.right >= t.left && (c = u, l = {
				left: Math.max(p.left, Math.min(p.right, t.left)),
				top: p.top
			});
			!n && (t.left >= p.right && t.top >= p.top || t.left >= p.left && t.top >= p.bottom) && (a = d + 1);
		}
	}
	return !n && c && (n = c, i = l, r = 0), n && q(n) ? Ud(n, i) : !n || r ? {
		node: e,
		offset: a
	} : Hd(n, i);
}
function Ud(e, t) {
	let n = e.nodeValue.length, r = document.createRange(), i;
	for (let a = 0; a < n; a++) {
		r.setEnd(e, a + 1), r.setStart(e, a);
		let n = Zd(r, 1);
		if (n.top != n.bottom && Wd(t, n)) {
			i = {
				node: e,
				offset: a + +(t.left >= (n.left + n.right) / 2)
			};
			break;
		}
	}
	return r.detach(), i || {
		node: e,
		offset: 0
	};
}
function Wd(e, t) {
	return e.left >= t.left - 1 && e.left <= t.right + 1 && e.top >= t.top - 1 && e.top <= t.bottom + 1;
}
function Gd(e, t) {
	let n = e.parentNode;
	return n && /^li$/i.test(n.nodeName) && t.left < e.getBoundingClientRect().left ? n : e;
}
function Kd(e, t, n) {
	let { node: r, offset: i } = Hd(t, n), a = -1;
	if (K(r) && !r.firstChild) {
		let e = r.getBoundingClientRect();
		a = e.left != e.right && n.left > (e.left + e.right) / 2 ? 1 : -1;
	}
	return e.docView.posFromDOM(r, i, a);
}
function qd(e, t, n, r) {
	let i = -1;
	for (let n = t, a = !1; n != e.dom;) {
		let t = e.docView.nearestDesc(n, !0), o;
		if (!t) return null;
		if (K(t.dom) && (t.node.isBlock && t.parent || !t.contentDOM) && ((o = t.dom.getBoundingClientRect()).width || o.height) && (t.node.isBlock && t.parent && !/^T(R|BODY|HEAD|FOOT)$/.test(t.dom.nodeName) && (!a && o.left > r.left || o.top > r.top ? i = t.posBefore : (!a && o.right < r.left || o.bottom < r.top) && (i = t.posAfter), a = !0), !t.contentDOM && i < 0 && !t.node.isText)) return (t.node.isBlock ? r.top < (o.top + o.bottom) / 2 : r.left < (o.left + o.right) / 2) ? t.posBefore : t.posAfter;
		n = t.dom.parentNode;
	}
	return i > -1 ? i : e.docView.posFromDOM(t, n, -1);
}
function Jd(e, t, n) {
	let r = e.childNodes.length;
	if (r && n.top < n.bottom) for (let i = Math.max(0, Math.min(r - 1, Math.floor(r * (t.top - n.top) / (n.bottom - n.top)) - 2)), a = i;;) {
		let n = e.childNodes[a];
		if (K(n)) {
			let e = n.getClientRects();
			for (let r = 0; r < e.length; r++) {
				let i = e[r];
				if (Wd(t, i)) return Jd(n, t, i);
			}
		}
		if ((a = (a + 1) % r) == i) break;
	}
	return e;
}
function Yd(e, t) {
	let n = e.dom.ownerDocument, r, i = 0, a = hd(n, t.left, t.top);
	a && ({node: r, offset: i} = a);
	let o = (e.root.elementFromPoint ? e.root : n).elementFromPoint(t.left, t.top), s;
	if (!o || !e.dom.contains(o)) {
		let n = e.dom.getBoundingClientRect();
		if (!Wd(t, n) || (o = Jd(e.dom, t, n), !o)) return null;
	}
	if (X) for (let e = o; r && e; e = ed(e)) e.draggable && (r = void 0);
	if (o = Gd(o, t), r) {
		if (Cd && K(r) && (i = Math.min(i, r.childNodes.length), i < r.childNodes.length)) {
			let e = r.childNodes[i], n;
			e.nodeName == "IMG" && (n = e.getBoundingClientRect()).right <= t.left && n.bottom > t.top && i++;
		}
		let n;
		Ad && i && K(r) && K(n = r.childNodes[i - 1]) && n.contentEditable == "false" && n.getBoundingClientRect().top >= t.top && i--, r == e.dom && i == r.childNodes.length - 1 && K(r.lastChild) && t.top > r.lastChild.getBoundingClientRect().bottom ? s = e.state.doc.content.size : i != 0 && K(r) && r.childNodes[i - 1].nodeName == "BR" || (s = qd(e, r, i, t));
	}
	s ??= Kd(e, o, t);
	let c = e.docView.nearestDesc(o, !0);
	return {
		pos: s,
		inside: c ? c.posAtStart - c.border : -1
	};
}
function Xd(e) {
	return e.top < e.bottom || e.left < e.right;
}
function Zd(e, t) {
	let n = e.getClientRects();
	if (n.length) {
		let e = n[t < 0 ? 0 : n.length - 1];
		if (Xd(e)) return e;
	}
	return Array.prototype.find.call(n, Xd) || e.getBoundingClientRect();
}
var Qd = /[\u0590-\u05f4\u0600-\u06ff\u0700-\u08ac]/;
function $d(e, t, n) {
	let { node: r, offset: i, atom: a } = e.docView.domFromPos(t, n < 0 ? -1 : 1), o = Ad || Cd;
	if (q(r)) {
		if (o && (Qd.test(r.nodeValue) || (n < 0 ? !i : i == r.nodeValue.length))) {
			let e = Zd(nd(r, i, i), n);
			if (Cd && i && /\s/.test(r.nodeValue[i - 1]) && i < r.nodeValue.length) {
				let t = Zd(nd(r, i - 1, i - 1), -1);
				if (t.top == e.top) {
					let n = Zd(nd(r, i, i + 1), -1);
					if (n.top != e.top) return ef(n, n.left < t.left);
				}
			}
			return e;
		}
		{
			let e = i, t = i, a = n < 0 ? 1 : -1;
			return n < 0 && !i ? (t++, a = -1) : n >= 0 && i == r.nodeValue.length ? (e--, a = 1) : n < 0 ? e-- : t++, ef(Zd(nd(r, e, t), a), a < 0);
		}
	}
	if (!e.state.doc.resolve(t - (a || 0)).parent.inlineContent) {
		if (a == null && i && (n < 0 || i == G(r))) {
			let e = r.childNodes[i - 1];
			if (K(e)) return tf(e.getBoundingClientRect(), !1);
		}
		if (a == null && i < G(r)) {
			let e = r.childNodes[i];
			if (K(e)) return tf(e.getBoundingClientRect(), !0);
		}
		return tf(r.getBoundingClientRect(), n >= 0);
	}
	if (a == null && i && (n < 0 || i == G(r))) {
		let e = r.childNodes[i - 1], t = q(e) ? nd(e, G(e) - +!o) : K(e) && (e.nodeName != "BR" || !e.nextSibling) ? e : null;
		if (t) return ef(Zd(t, 1), !1);
	}
	if (a == null && i < G(r)) {
		let e = r.childNodes[i];
		for (; e.pmViewDesc && e.pmViewDesc.ignoreForCoords;) e = e.nextSibling;
		let t = e ? q(e) ? nd(e, 0, +!o) : K(e) ? e : null : null;
		if (t) return ef(Zd(t, -1), !0);
	}
	return ef(Zd(q(r) ? nd(r) : r, -n), n >= 0);
}
function ef(e, t) {
	if (e.width == 0) return e;
	let n = t ? e.left : e.right;
	return {
		top: e.top,
		bottom: e.bottom,
		left: n,
		right: n
	};
}
function tf(e, t) {
	if (e.height == 0) return e;
	let n = t ? e.top : e.bottom;
	return {
		top: n,
		bottom: n,
		left: e.left,
		right: e.right
	};
}
function nf(e, t, n) {
	let r = e.state, i = e.root.activeElement;
	r != t && e.updateState(t), i != e.dom && e.focus();
	try {
		return n();
	} finally {
		r != t && e.updateState(r), i != e.dom && i && i.focus();
	}
}
function rf(e, t, n) {
	let r = t.selection, i = n == "up" ? r.$from : r.$to;
	return nf(e, t, () => {
		let { node: t } = e.docView.domFromPos(i.pos, n == "up" ? -1 : 1);
		for (;;) {
			let n = e.docView.nearestDesc(t, !0);
			if (!n) break;
			if (n.node.isBlock) {
				t = n.contentDOM || n.dom;
				break;
			}
			t = n.dom.parentNode;
		}
		let r = $d(e, i.pos, 1);
		for (let e = t.firstChild; e; e = e.nextSibling) {
			let t;
			if (K(e)) t = e.getClientRects();
			else if (q(e)) t = nd(e, 0, e.nodeValue.length).getClientRects();
			else continue;
			for (let e = 0; e < t.length; e++) {
				let i = t[e];
				if (i.bottom > i.top + 1 && (n == "up" ? r.top - i.top > (i.bottom - r.top) * 2 : i.bottom - r.bottom > (r.bottom - i.top) * 2)) return !1;
			}
		}
		return !0;
	});
}
var af = /[\u0590-\u08ac]/;
function of(e, t, n) {
	let { $head: r } = t.selection;
	if (!r.parent.isTextblock) return !1;
	let i = r.parentOffset, a = !i, o = i == r.parent.content.size, s = e.domSelection();
	return s ? !af.test(r.parent.textContent) || !s.modify ? n == "left" || n == "backward" ? a : o : nf(e, t, () => {
		let { focusNode: t, focusOffset: i, anchorNode: a, anchorOffset: o } = e.domSelectionRange(), c = s.caretBidiLevel;
		s.modify("move", n, "character");
		let l = r.depth ? e.docView.domAfterPos(r.before()) : e.dom, { focusNode: u, focusOffset: d } = e.domSelectionRange(), f = u && !sd(l, u) || t == u && i == d;
		try {
			s.collapse(a, o), t && (t != a || i != o) && s.extend && s.extend(t, i);
		} catch {}
		return c != null && (s.caretBidiLevel = c), f;
	}) : r.pos == r.start() || r.pos == r.end();
}
var sf = null, cf = null, lf = !1;
function uf(e, t, n) {
	return sf == t && cf == n ? lf : (sf = t, cf = n, lf = n == "up" || n == "down" ? rf(e, t, n) : of(e, t, n));
}
var df = 0, ff = 1, pf = 2, mf = 3, hf = class {
	constructor(e, t, n, r) {
		this.parent = e, this.children = t, this.dom = n, this.contentDOM = r, this.dirty = df, n.pmViewDesc = this;
	}
	matchesWidget(e) {
		return !1;
	}
	matchesMark(e) {
		return !1;
	}
	matchesNode(e, t, n) {
		return !1;
	}
	matchesHack(e) {
		return !1;
	}
	parseRule(e) {
		return null;
	}
	stopEvent(e) {
		return !1;
	}
	get size() {
		let e = 0;
		for (let t = 0; t < this.children.length; t++) e += this.children[t].size;
		return e;
	}
	get border() {
		return 0;
	}
	destroy() {
		this.parent = void 0, this.dom.pmViewDesc == this && (this.dom.pmViewDesc = void 0);
		for (let e = 0; e < this.children.length; e++) this.children[e].destroy();
	}
	posBeforeChild(e) {
		for (let t = 0, n = this.posAtStart;; t++) {
			let r = this.children[t];
			if (r == e) return n;
			n += r.size;
		}
	}
	get posBefore() {
		return this.parent.posBeforeChild(this);
	}
	get posAtStart() {
		return this.parent ? this.parent.posBeforeChild(this) + this.border : 0;
	}
	get posAfter() {
		return this.posBefore + this.size;
	}
	get posAtEnd() {
		return this.posAtStart + this.size - 2 * this.border;
	}
	localPosFromDOM(e, t, n) {
		if (this.contentDOM && sd(this.contentDOM, e)) {
			if (n < 0) {
				let n, r;
				if (e == this.contentDOM) n = e.childNodes[t - 1];
				else {
					for (; e.parentNode != this.contentDOM;) e = e.parentNode;
					n = e.previousSibling;
				}
				for (; n && !((r = n.pmViewDesc) && r.parent == this);) n = n.previousSibling;
				return n ? this.posBeforeChild(r) + r.size : this.posAtStart;
			}
			{
				let n, r;
				if (e == this.contentDOM) n = e.childNodes[t];
				else {
					for (; e.parentNode != this.contentDOM;) e = e.parentNode;
					n = e.nextSibling;
				}
				for (; n && !((r = n.pmViewDesc) && r.parent == this);) n = n.nextSibling;
				return n ? this.posBeforeChild(r) : this.posAtEnd;
			}
		}
		let r;
		if (e == this.dom && this.contentDOM) r = t > W(this.contentDOM);
		else if (this.contentDOM && this.contentDOM != this.dom && this.dom.contains(this.contentDOM)) {
			let n = e.compareDocumentPosition(this.contentDOM);
			r = n & 16 ? t > Array.from(e.childNodes).findIndex((e) => e.contains(this.contentDOM)) : !(n & 4);
		} else if (this.dom.firstChild) {
			if (t == 0) for (let t = e;; t = t.parentNode) {
				if (t == this.dom) {
					r = !1;
					break;
				}
				if (t.previousSibling) break;
			}
			if (r == null && t == e.childNodes.length) for (let t = e;; t = t.parentNode) {
				if (t == this.dom) {
					r = !0;
					break;
				}
				if (t.nextSibling) break;
			}
		}
		return r ?? n > 0 ? this.posAtEnd : this.posAtStart;
	}
	nearestDesc(e, t = !1) {
		for (let n = !0, r = e; r; r = r.parentNode) {
			let i = this.getDesc(r), a;
			if (i && (!t || i.node)) {
				if (n && (a = i.nodeDOM) && !(K(a) ? sd(a, e) : a == e)) n = !1;
				else return i;
			}
		}
	}
	getDesc(e) {
		let t = e.pmViewDesc;
		for (let e = t; e; e = e.parent) if (e == this) return t;
	}
	posFromDOM(e, t, n) {
		for (let r = e; r; r = r.parentNode) {
			let i = this.getDesc(r);
			if (i) return i.localPosFromDOM(e, t, n);
		}
		return -1;
	}
	descAt(e) {
		for (let t = 0, n = 0; t < this.children.length; t++) {
			let r = this.children[t], i = n + r.size;
			if (n == e && i != n) {
				for (; !r.border && r.children.length;) for (let e = 0; e < r.children.length; e++) {
					let t = r.children[e];
					if (t.size) {
						r = t;
						break;
					}
				}
				return r;
			}
			if (e < i) return r.descAt(e - n - r.border);
			n = i;
		}
	}
	domFromPos(e, t) {
		if (!this.contentDOM) return {
			node: this.dom,
			offset: 0,
			atom: e + 1
		};
		let n = 0, r = 0;
		for (let t = 0; n < this.children.length; n++) {
			let i = this.children[n], a = t + i.size;
			if (a > e) {
				r = e - t;
				break;
			}
			t = a;
		}
		if (r) return this.children[n].domFromPos(r - this.children[n].border, t);
		for (; n > 0; n--) {
			let e = this.children[n - 1];
			if (e.size || !(e instanceof Sf || e instanceof gf && e.side >= 0)) break;
		}
		if (t <= 0) {
			let e, r = !0;
			for (; e = n ? this.children[n - 1] : null, e && e.dom.parentNode != this.contentDOM; n--, r = !1);
			return e && t && r && !e.border && !e.domAtom ? e.domFromPos(e.size, t) : {
				node: this.contentDOM,
				offset: e ? W(e.dom) + 1 : 0
			};
		}
		{
			let e, r = !0;
			for (; e = n < this.children.length ? this.children[n] : null, e && e.dom.parentNode != this.contentDOM; n++, r = !1);
			return e && r && !e.border && !e.domAtom ? e.domFromPos(0, t) : {
				node: this.contentDOM,
				offset: e ? W(e.dom) : this.contentDOM.childNodes.length
			};
		}
	}
	parseRange(e, t, n = 0) {
		if (this.children.length == 0) return {
			node: this.contentDOM,
			from: e,
			to: t,
			fromOffset: 0,
			toOffset: this.contentDOM.childNodes.length
		};
		let r = -1, i = -1;
		for (let a = n, o = 0;; o++) {
			let n = this.children[o], s = a + n.size;
			if (r == -1 && e <= s) {
				let i = a + n.border;
				if (e >= i && t <= s - n.border && n.node && n.contentDOM && this.contentDOM.contains(n.contentDOM)) return n.parseRange(e, t, i);
				e = a;
				for (let t = o; t > 0; t--) {
					let n = this.children[t - 1];
					if (n.size && n.dom.parentNode == this.contentDOM && !n.emptyChildAt(1)) {
						r = W(n.dom) + 1;
						break;
					}
					e -= n.size;
				}
				r == -1 && (r = 0);
			}
			if (r > -1 && (s > t || o == this.children.length - 1)) {
				t = s;
				for (let e = o + 1; e < this.children.length; e++) {
					let n = this.children[e];
					if (n.size && n.dom.parentNode == this.contentDOM && !n.emptyChildAt(-1)) {
						i = W(n.dom);
						break;
					}
					t += n.size;
				}
				i == -1 && (i = this.contentDOM.childNodes.length);
				break;
			}
			a = s;
		}
		return {
			node: this.contentDOM,
			from: e,
			to: t,
			fromOffset: r,
			toOffset: i
		};
	}
	emptyChildAt(e) {
		if (this.border || !this.contentDOM || !this.children.length) return !1;
		let t = this.children[e < 0 ? 0 : this.children.length - 1];
		return t.size == 0 || t.emptyChildAt(e);
	}
	domAfterPos(e) {
		let { node: t, offset: n } = this.domFromPos(e, 0);
		if (!K(t) || n == t.childNodes.length) throw RangeError("No node after pos " + e);
		return t.childNodes[n];
	}
	setSelection(e, t, n, r = !1) {
		let i = Math.min(e, t), a = Math.max(e, t);
		for (let o = 0, s = 0; o < this.children.length; o++) {
			let c = this.children[o], l = s + c.size;
			if (i > s && a < l) return c.setSelection(e - s - c.border, t - s - c.border, n, r);
			s = l;
		}
		let o = this.domFromPos(e, e ? -1 : 1), s = t == e ? o : this.domFromPos(t, t ? -1 : 1), c = n.root.getSelection(), l = n.domSelectionRange(), u = !1;
		if ((Cd || X) && e == t) {
			let { node: e, offset: t } = o;
			if (q(e)) {
				if (u = !!(t && e.nodeValue[t - 1] == "\n"), u && t == e.nodeValue.length) for (let t = e, n; t; t = t.parentNode) {
					if (n = t.nextSibling) {
						n.nodeName == "BR" && (o = s = {
							node: n.parentNode,
							offset: W(n) + 1
						});
						break;
					}
					let e = t.pmViewDesc;
					if (e && e.node && e.node.isBlock) break;
				}
			} else {
				let n = e.childNodes[t - 1];
				u = n && (n.nodeName == "BR" || n.contentEditable == "false");
			}
		}
		if (Cd && l.focusNode && l.focusNode != s.node && K(l.focusNode)) {
			let e = l.focusNode.childNodes[l.focusOffset];
			e && e.contentEditable == "false" && (r = !0);
		}
		if (!(r || u && X) && id(o.node, o.offset, l.anchorNode, l.anchorOffset) && id(s.node, s.offset, l.focusNode, l.focusOffset)) return;
		let d = !1;
		if ((c.extend || e == t) && !(u && Cd)) {
			c.collapse(o.node, o.offset);
			try {
				e != t && c.extend(s.node, s.offset), d = !0;
			} catch {}
		}
		if (!d) {
			if (e > t) {
				let e = o;
				o = s, s = e;
			}
			let n = document.createRange();
			n.setEnd(s.node, s.offset), n.setStart(o.node, o.offset), c.removeAllRanges(), c.addRange(n);
		}
	}
	ignoreMutation(e) {
		return !this.contentDOM && e.type != "selection";
	}
	get contentLost() {
		return this.contentDOM && this.contentDOM != this.dom && !this.dom.contains(this.contentDOM);
	}
	markDirty(e, t) {
		for (let n = 0, r = 0; r < this.children.length; r++) {
			let i = this.children[r], a = n + i.size;
			if (n == a ? e <= a && t >= n : e < a && t > n) {
				let r = n + i.border, o = a - i.border;
				if (e >= r && t <= o) {
					this.dirty = e == n || t == a ? pf : ff, e == r && t == o && (i.contentLost || i.dom.parentNode != this.contentDOM) ? i.dirty = mf : i.markDirty(e - r, t - r);
					return;
				}
				i.dirty = i.dom == i.contentDOM && i.dom.parentNode == this.contentDOM && !i.children.length ? pf : mf;
			}
			n = a;
		}
		this.dirty = pf;
	}
	markParentsDirty() {
		let e = 1;
		for (let t = this.parent; t; t = t.parent, e++) {
			let n = e == 1 ? pf : ff;
			t.dirty < n && (t.dirty = n);
		}
	}
	get domAtom() {
		return !1;
	}
	get ignoreForCoords() {
		return !1;
	}
	get ignoreForSelection() {
		return !1;
	}
	isText(e) {
		return !1;
	}
}, gf = class extends hf {
	constructor(e, t, n, r) {
		let i, a = t.type.toDOM;
		if (typeof a == "function" && (a = a(n, () => {
			if (!i) return r;
			if (i.parent) return i.parent.posBeforeChild(i);
		})), !t.type.spec.raw) {
			let e = K(a) ? a : document.createElement("span");
			e != a && (e.appendChild(a), a = e), e.hasAttribute("contenteditable") || (e.contentEditable = "false"), e.classList.add("ProseMirror-widget");
		}
		super(e, [], a, null), this.widget = t, this.widget = t, i = this;
	}
	matchesWidget(e) {
		return this.dirty == df && e.type.eq(this.widget.type);
	}
	parseRule() {
		return { ignore: !0 };
	}
	stopEvent(e) {
		let t = this.widget.spec.stopEvent;
		return t ? t(e) : !1;
	}
	ignoreMutation(e) {
		return e.type != "selection" || this.widget.spec.ignoreSelection;
	}
	destroy() {
		this.widget.type.destroy(this.dom), super.destroy();
	}
	get domAtom() {
		return !0;
	}
	get ignoreForSelection() {
		return !!this.widget.type.spec.relaxedSide;
	}
	get side() {
		return this.widget.type.side;
	}
}, _f = class extends hf {
	constructor(e, t, n, r) {
		super(e, [], t, null), this.textDOM = n, this.text = r;
	}
	get size() {
		return this.text.length;
	}
	localPosFromDOM(e, t) {
		return e == this.textDOM ? this.posAtStart + t : this.posAtStart + (t ? this.size : 0);
	}
	domFromPos(e) {
		return {
			node: this.textDOM,
			offset: e
		};
	}
	ignoreMutation(e) {
		return e.type === "characterData" && e.target.nodeValue == e.oldValue;
	}
}, vf = class e extends hf {
	constructor(e, t, n, r, i) {
		super(e, [], n, r), this.mark = t, this.spec = i;
	}
	static create(t, n, r, i) {
		let a = i.nodeViews[n.type.name], o = a && a(n, i, r);
		return o && o.dom || (o = ms.renderSpec(document, n.type.spec.toDOM(n, r), null, n.attrs)), new e(t, n, o.dom, o.contentDOM || o.dom, o);
	}
	parseRule() {
		return this.dirty & mf || this.mark.type.spec.reparseInView ? null : {
			mark: this.mark.type.name,
			attrs: this.mark.attrs,
			contentElement: this.contentDOM
		};
	}
	matchesMark(e) {
		return this.dirty != mf && this.mark.eq(e);
	}
	markDirty(e, t) {
		if (super.markDirty(e, t), this.dirty != df) {
			let e = this.parent;
			for (; !e.node;) e = e.parent;
			e.dirty < this.dirty && (e.dirty = this.dirty), this.dirty = df;
		}
	}
	slice(t, n, r) {
		let i = e.create(this.parent, this.mark, !0, r), a = this.children, o = this.size;
		n < o && (a = zf(a, n, o, r)), t > 0 && (a = zf(a, 0, t, r));
		for (let e = 0; e < a.length; e++) a[e].parent = i;
		return i.children = a, i;
	}
	ignoreMutation(e) {
		return this.spec.ignoreMutation ? this.spec.ignoreMutation(e) : super.ignoreMutation(e);
	}
	destroy() {
		this.spec.destroy && this.spec.destroy(), super.destroy();
	}
}, yf = class e extends hf {
	constructor(e, t, n, r, i, a, o) {
		super(e, [], i, a), this.node = t, this.outerDeco = n, this.innerDeco = r, this.nodeDOM = o;
	}
	static create(t, n, r, i, a, o) {
		let s = a.nodeViews[n.type.name], c, l = s && s(n, a, () => {
			if (!c) return o;
			if (c.parent) return c.parent.posBeforeChild(c);
		}, r, i), u = l && l.dom, d = l && l.contentDOM;
		if (n.isText) {
			if (!u) u = document.createTextNode(n.text);
			else if (!q(u)) throw RangeError("Text must be rendered as a DOM text node");
		} else if (!u) {
			let e = ms.renderSpec(document, n.type.spec.toDOM(n), null, n.attrs);
			({dom: u, contentDOM: d} = e);
		}
		!d && !n.isText && u.nodeName != "BR" && (u.hasAttribute("contenteditable") || (u.contentEditable = "false"), n.type.spec.draggable && (u.draggable = !0));
		let f = u;
		return u = Af(u, r, n), l ? c = new Cf(t, n, r, i, u, d || null, f, l) : n.isText ? new xf(t, n, r, i, u, f) : new e(t, n, r, i, u, d || null, f);
	}
	parseRule(e) {
		if (this.node.type.spec.reparseInView) return null;
		let t = {
			node: this.node.type.name,
			attrs: this.node.attrs
		};
		if (this.node.type.whitespace == "pre" && (t.preserveWhitespace = "full"), !this.contentDOM) t.getContent = () => this.node.content;
		else if (!this.contentLost) t.contentElement = this.contentDOM;
		else {
			for (let e = this.children.length - 1; e >= 0; e--) {
				let n = this.children[e];
				if (this.dom.contains(n.dom.parentNode)) {
					t.contentElement = n.dom.parentNode;
					break;
				}
			}
			if (!t.contentElement) {
				let n = e && e.find((t) => K(t) && e.indexOf(t.parentNode) < 0 && this.dom.contains(t));
				n ? t.contentElement = n : t.getContent = () => I.empty;
			}
		}
		return t;
	}
	matchesNode(e, t, n) {
		return this.dirty == df && e.eq(this.node) && jf(t, this.outerDeco) && n.eq(this.innerDeco);
	}
	get size() {
		return this.node.nodeSize;
	}
	get border() {
		return +!this.node.isLeaf;
	}
	updateChildren(e, t) {
		let n = this.node.inlineContent, r = t, i = e.composing ? this.localCompositionInfo(e, t) : null, a = i && i.pos > -1 ? i : null, o = i && i.pos < 0, s = new Nf(this, a && a.node, e), c = this.node.inlineContent, l = c && this.node.isBlock;
		If(this.node, this.innerDeco, (t, i, a) => {
			t.spec.marks ? s.syncToMarks(t.spec.marks, n, e, i) : t.type.side >= 0 && !a && s.syncToMarks(i == this.node.childCount ? L.none : this.node.child(i).marks, n, e, i), s.placeWidget(t, e, r);
		}, (t, a, u, d) => {
			s.syncToMarks(t.marks, n, e, d), l && t.isAtom && !t.isText && s.addHackNode("IMG", s.top);
			let f;
			s.findNodeMatch(t, a, u, d) || o && e.state.selection.from > r && e.state.selection.to < r + t.nodeSize && (f = s.findIndexWithChild(i.node)) > -1 && s.updateNodeAt(t, a, u, f, e) || s.updateNextNode(t, a, u, e, d, r) || s.addNode(t, a, u, e, r), r += t.nodeSize, c && (l = t.isAtom && !t.isText);
		}), s.syncToMarks([], n, e, 0), this.node.isTextblock && s.addTextblockHacks(), s.destroyRest(), (s.changed || this.dirty == pf) && (a && this.protectLocalComposition(e, a), wf(this.contentDOM, this.children, e), Ed && Lf(this.dom));
	}
	localCompositionInfo(e, t) {
		let { from: n, to: r } = e.state.selection;
		if (!(e.state.selection instanceof H) || n < t || r > t + this.node.content.size) return null;
		let i = e.input.compositionNode;
		if (!i || !this.dom.contains(i.parentNode)) return null;
		if (this.node.inlineContent) {
			let e = i.nodeValue, a = Rf(this.node.content, e, n - t, r - t);
			return a < 0 ? null : {
				node: i,
				pos: a,
				text: e
			};
		}
		return {
			node: i,
			pos: -1,
			text: ""
		};
	}
	protectLocalComposition(e, { node: t, pos: n, text: r }) {
		if (this.getDesc(t)) return;
		let i = t;
		for (; i.parentNode != this.contentDOM; i = i.parentNode) {
			for (; i.previousSibling;) i.parentNode.removeChild(i.previousSibling);
			for (; i.nextSibling;) i.parentNode.removeChild(i.nextSibling);
			i.pmViewDesc && (i.pmViewDesc = void 0);
		}
		let a = new _f(this, i, t, r);
		e.input.compositionNodes.push(a), this.children = zf(this.children, n, n + r.length, e, a);
	}
	update(e, t, n, r) {
		return this.dirty == mf || !e.sameMarkup(this.node) ? !1 : (this.updateInner(e, t, n, r), !0);
	}
	updateInner(e, t, n, r) {
		this.updateOuterDeco(t), this.node = e, this.innerDeco = n, this.contentDOM && this.updateChildren(r, this.posAtStart), this.dirty = df;
	}
	updateOuterDeco(e) {
		if (jf(e, this.outerDeco)) return;
		let t = !K(this.nodeDOM), n = this.dom;
		this.dom = Of(this.dom, this.nodeDOM, Df(this.outerDeco, this.node, t), Df(e, this.node, t)), this.dom != n && (n.pmViewDesc = void 0, this.dom.pmViewDesc = this), this.outerDeco = e;
	}
	selectNode() {
		K(this.nodeDOM) && (this.nodeDOM.classList.add("ProseMirror-selectednode"), (this.contentDOM || !this.node.type.spec.draggable) && (this.nodeDOM.draggable = !0));
	}
	deselectNode() {
		K(this.nodeDOM) && (this.nodeDOM.classList.remove("ProseMirror-selectednode"), (this.contentDOM || !this.node.type.spec.draggable) && this.nodeDOM.removeAttribute("draggable"));
	}
	get domAtom() {
		return this.node.isAtom;
	}
};
function bf(e, t, n, r, i) {
	Af(r, t, e);
	let a = new yf(void 0, e, t, n, r, r, r);
	return a.contentDOM && a.updateChildren(i, 0), a;
}
var xf = class e extends yf {
	constructor(e, t, n, r, i, a) {
		super(e, t, n, r, i, null, a);
	}
	parseRule() {
		let e = this.nodeDOM.parentNode;
		for (; e && e != this.dom && !e.pmIsDeco;) e = e.parentNode;
		return { skip: e || !0 };
	}
	update(e, t, n, r) {
		return this.dirty == mf || this.dirty != df && !this.inParent() || !e.sameMarkup(this.node) ? !1 : (this.updateOuterDeco(t), (this.dirty != df || e.text != this.node.text) && e.text != this.nodeDOM.nodeValue && (this.nodeDOM.nodeValue = e.text, r.trackWrites == this.nodeDOM && (r.trackWrites = null)), this.node = e, this.dirty = df, !0);
	}
	inParent() {
		let e = this.parent.contentDOM;
		for (let t = this.nodeDOM; t; t = t.parentNode) if (t == e) return !0;
		return !1;
	}
	domFromPos(e) {
		return {
			node: this.nodeDOM,
			offset: e
		};
	}
	localPosFromDOM(e, t, n) {
		return e == this.nodeDOM ? this.posAtStart + Math.min(t, this.node.text.length) : super.localPosFromDOM(e, t, n);
	}
	ignoreMutation(e) {
		return e.type != "characterData" && e.type != "selection";
	}
	slice(t, n, r) {
		let i = this.node.cut(t, n), a = document.createTextNode(i.text);
		return new e(this.parent, i, this.outerDeco, this.innerDeco, a, a);
	}
	markDirty(e, t) {
		super.markDirty(e, t), this.dom != this.nodeDOM && (e == 0 || t == this.nodeDOM.nodeValue.length) && (this.dirty = mf);
	}
	get domAtom() {
		return !1;
	}
	isText(e) {
		return this.node.text == e;
	}
}, Sf = class extends hf {
	parseRule() {
		return { ignore: !0 };
	}
	matchesHack(e) {
		return this.dirty == df && this.dom.nodeName == e;
	}
	get domAtom() {
		return !0;
	}
	get ignoreForCoords() {
		return this.dom.nodeName == "IMG";
	}
}, Cf = class extends yf {
	constructor(e, t, n, r, i, a, o, s) {
		super(e, t, n, r, i, a, o), this.spec = s;
	}
	update(e, t, n, r) {
		if (this.dirty == mf) return !1;
		if (this.spec.update && (this.node.type == e.type || this.spec.multiType)) {
			let i = this.spec.update(e, t, n);
			return i && this.updateInner(e, t, n, r), i;
		}
		return !this.contentDOM && !e.isLeaf ? !1 : super.update(e, t, n, r);
	}
	selectNode() {
		this.spec.selectNode ? this.spec.selectNode() : super.selectNode();
	}
	deselectNode() {
		this.spec.deselectNode ? this.spec.deselectNode() : super.deselectNode();
	}
	setSelection(e, t, n, r) {
		this.spec.setSelection ? this.spec.setSelection(e, t, n.root) : super.setSelection(e, t, n, r);
	}
	destroy() {
		this.spec.destroy && this.spec.destroy(), super.destroy();
	}
	stopEvent(e) {
		return this.spec.stopEvent ? this.spec.stopEvent(e) : !1;
	}
	ignoreMutation(e) {
		return this.spec.ignoreMutation ? this.spec.ignoreMutation(e) : super.ignoreMutation(e);
	}
};
function wf(e, t, n) {
	let r = e.firstChild, i = !1;
	for (let a = 0; a < t.length; a++) {
		let o = t[a], s = o.dom;
		if (s.parentNode == e) {
			for (; s != r;) r = Mf(r), i = !0;
			r = r.nextSibling;
		} else i = !0, e.insertBefore(s, r);
		if (o instanceof vf) {
			let t = r ? r.previousSibling : e.lastChild;
			wf(o.contentDOM, o.children, n), r = t ? t.nextSibling : e.firstChild;
		}
	}
	for (; r;) r = Mf(r), i = !0;
	i && n.trackWrites == e && (n.trackWrites = null);
}
var Tf = function(e) {
	e && (this.nodeName = e);
};
Tf.prototype = Object.create(null);
var Ef = [new Tf()];
function Df(e, t, n) {
	if (e.length == 0) return Ef;
	let r = n ? Ef[0] : new Tf(), i = [r];
	for (let a = 0; a < e.length; a++) {
		let o = e[a].type.attrs;
		if (o) {
			o.nodeName && i.push(r = new Tf(o.nodeName));
			for (let e in o) {
				let a = o[e];
				a != null && (n && i.length == 1 && i.push(r = new Tf(t.isInline ? "span" : "div")), e == "class" ? r.class = (r.class ? r.class + " " : "") + a : e == "style" ? r.style = (r.style ? r.style + ";" : "") + a : e != "nodeName" && (r[e] = a));
			}
		}
	}
	return i;
}
function Of(e, t, n, r) {
	if (n == Ef && r == Ef) return t;
	let i = t;
	for (let t = 0; t < r.length; t++) {
		let a = r[t], o = n[t];
		if (t) {
			let t;
			o && o.nodeName == a.nodeName && i != e && (t = i.parentNode) && t.nodeName.toLowerCase() == a.nodeName || (t = document.createElement(a.nodeName), t.pmIsDeco = !0, t.appendChild(i), o = Ef[0]), i = t;
		}
		kf(i, o || Ef[0], a);
	}
	return i;
}
function kf(e, t, n) {
	for (let r in t) r != "class" && r != "style" && r != "nodeName" && !(r in n) && e.removeAttribute(r);
	for (let r in n) r != "class" && r != "style" && r != "nodeName" && n[r] != t[r] && e.setAttribute(r, n[r]);
	if (t.class != n.class) {
		let r = t.class ? t.class.split(" ").filter(Boolean) : [], i = n.class ? n.class.split(" ").filter(Boolean) : [];
		for (let t = 0; t < r.length; t++) i.indexOf(r[t]) == -1 && e.classList.remove(r[t]);
		for (let t = 0; t < i.length; t++) r.indexOf(i[t]) == -1 && e.classList.add(i[t]);
		e.classList.length == 0 && e.removeAttribute("class");
	}
	if (t.style != n.style) {
		if (t.style) {
			let n = /\s*([\w\-\xa1-\uffff]+)\s*:(?:"(?:\\.|[^"])*"|'(?:\\.|[^'])*'|\(.*?\)|[^;])*/g, r;
			for (; r = n.exec(t.style);) e.style.removeProperty(r[1]);
		}
		n.style && (e.style.cssText += n.style);
	}
}
function Af(e, t, n) {
	return Of(e, e, Ef, Df(t, n, !K(e)));
}
function jf(e, t) {
	if (e.length != t.length) return !1;
	for (let n = 0; n < e.length; n++) if (!e[n].type.eq(t[n].type)) return !1;
	return !0;
}
function Mf(e) {
	let t = e.nextSibling;
	return e.parentNode.removeChild(e), t;
}
var Nf = class {
	constructor(e, t, n) {
		this.lock = t, this.view = n, this.index = 0, this.stack = [], this.changed = !1, this.top = e, this.preMatch = Pf(e.node.content, e);
	}
	destroyBetween(e, t) {
		if (e != t) {
			for (let n = e; n < t; n++) this.top.children[n].destroy();
			this.top.children.splice(e, t - e), this.changed = !0;
		}
	}
	destroyRest() {
		this.destroyBetween(this.index, this.top.children.length);
	}
	syncToMarks(e, t, n, r) {
		let i = 0, a = this.stack.length >> 1, o = Math.min(a, e.length);
		for (; i < o && (i == a - 1 ? this.top : this.stack[i + 1 << 1]).matchesMark(e[i]) && e[i].type.spec.spanning !== !1;) i++;
		for (; i < a;) this.destroyRest(), this.top.dirty = df, this.index = this.stack.pop(), this.top = this.stack.pop(), a--;
		for (; a < e.length;) {
			this.stack.push(this.top, this.index + 1);
			let i = -1, o = this.top.children.length;
			r < this.preMatch.index && (o = Math.min(this.index + 3, o));
			for (let t = this.index; t < o; t++) {
				let n = this.top.children[t];
				if (n.matchesMark(e[a]) && !this.isLocked(n.dom)) {
					i = t;
					break;
				}
			}
			if (i < 0 && this.index < this.top.children.length) {
				let t = this.top.children[this.index];
				t instanceof vf && t.dirty != mf && t.mark.type == e[a].type && t.spec.update && !this.isLocked(t.dom) && t.spec.update(e[a]) && (t.mark = e[a], i = this.index, this.changed = !0);
			}
			if (i > -1) i > this.index && (this.changed = !0, this.destroyBetween(this.index, i)), this.top = this.top.children[this.index];
			else {
				let r = vf.create(this.top, e[a], t, n);
				this.top.children.splice(this.index, 0, r), this.top = r, this.changed = !0;
			}
			this.index = 0, a++;
		}
	}
	findNodeMatch(e, t, n, r) {
		let i = -1, a;
		if (r >= this.preMatch.index && (a = this.preMatch.matches[r - this.preMatch.index]).parent == this.top && a.matchesNode(e, t, n)) i = this.top.children.indexOf(a, this.index);
		else for (let r = this.index, a = Math.min(this.top.children.length, r + 5); r < a; r++) {
			let a = this.top.children[r];
			if (a.matchesNode(e, t, n) && !this.preMatch.matched.has(a)) {
				i = r;
				break;
			}
		}
		return i < 0 ? !1 : (this.destroyBetween(this.index, i), this.index++, !0);
	}
	updateNodeAt(e, t, n, r, i) {
		let a = this.top.children[r];
		return a.dirty == mf && a.dom == a.contentDOM && (a.dirty = pf), a.update(e, t, n, i) ? (this.destroyBetween(this.index, r), this.index++, !0) : !1;
	}
	findIndexWithChild(e) {
		for (;;) {
			let t = e.parentNode;
			if (!t) return -1;
			if (t == this.top.contentDOM) {
				let t = e.pmViewDesc;
				if (t) {
					for (let e = this.index; e < this.top.children.length; e++) if (this.top.children[e] == t) return e;
				}
				return -1;
			}
			e = t;
		}
	}
	updateNextNode(e, t, n, r, i, a) {
		for (let o = this.index; o < this.top.children.length; o++) {
			let s = this.top.children[o];
			if (s instanceof yf) {
				let c = this.preMatch.matched.get(s);
				if (c != null && c != i) return !1;
				let l = s.dom, u, d = this.isLocked(l) && !(e.isText && s.node && s.node.isText && s.nodeDOM.nodeValue == e.text && s.dirty != mf && jf(t, s.outerDeco));
				if (!d && s.update(e, t, n, r)) return this.destroyBetween(this.index, o), s.dom != l && (this.changed = !0), this.index++, !0;
				if (!d && (u = this.recreateWrapper(s, e, t, n, r, a))) return this.destroyBetween(this.index, o), this.top.children[this.index] = u, u.contentDOM && (u.dirty = pf, u.updateChildren(r, a + 1), u.dirty = df), this.changed = !0, this.index++, !0;
				break;
			}
		}
		return !1;
	}
	recreateWrapper(e, t, n, r, i, a) {
		if (e.dirty || t.isAtom || !e.children.length || !e.node.content.eq(t.content) || !jf(n, e.outerDeco) || !r.eq(e.innerDeco)) return null;
		let o = yf.create(this.top, t, n, r, i, a);
		if (o.contentDOM) {
			o.children = e.children, e.children = [];
			for (let e of o.children) e.parent = o;
		}
		return e.destroy(), o;
	}
	addNode(e, t, n, r, i) {
		let a = yf.create(this.top, e, t, n, r, i);
		a.contentDOM && a.updateChildren(r, i + 1), this.top.children.splice(this.index++, 0, a), this.changed = !0;
	}
	placeWidget(e, t, n) {
		let r = this.index < this.top.children.length ? this.top.children[this.index] : null;
		if (r && r.matchesWidget(e) && (e == r.widget || !r.widget.type.toDOM.parentNode)) this.index++;
		else {
			let r = new gf(this.top, e, t, n);
			this.top.children.splice(this.index++, 0, r), this.changed = !0;
		}
	}
	addTextblockHacks() {
		let e = this.top.children[this.index - 1], t = this.top;
		for (; e instanceof vf;) t = e, e = t.children[t.children.length - 1];
		(!e || !(e instanceof xf) || /\n$/.test(e.node.text) || this.view.requiresGeckoHackNode && /\s$/.test(e.node.text)) && ((X || Y) && e && e.dom.contentEditable == "false" && this.addHackNode("IMG", t), this.addHackNode("BR", this.top));
	}
	addHackNode(e, t) {
		if (t == this.top && this.index < t.children.length && t.children[this.index].matchesHack(e)) this.index++;
		else {
			let n = document.createElement(e);
			e == "IMG" && (n.className = "ProseMirror-separator", n.alt = ""), e == "BR" && (n.className = "ProseMirror-trailingBreak");
			let r = new Sf(this.top, [], n, null);
			t == this.top ? t.children.splice(this.index++, 0, r) : t.children.push(r), this.changed = !0;
		}
	}
	isLocked(e) {
		return this.lock && (e == this.lock || K(e) && e.contains(this.lock.parentNode));
	}
};
function Pf(e, t) {
	let n = t, r = n.children.length, i = e.childCount, a = /* @__PURE__ */ new Map(), o = [];
	outer: for (; i > 0;) {
		let s;
		for (;;) if (r) {
			let e = n.children[r - 1];
			if (e instanceof vf) n = e, r = e.children.length;
			else {
				s = e, r--;
				break;
			}
		} else if (n == t) break outer;
		else r = n.parent.children.indexOf(n), n = n.parent;
		let c = s.node;
		if (c) {
			if (c != e.child(i - 1)) break;
			--i, a.set(s, i), o.push(s);
		}
	}
	return {
		index: i,
		matched: a,
		matches: o.reverse()
	};
}
function Ff(e, t) {
	return e.type.side - t.type.side;
}
function If(e, t, n, r) {
	let i = t.locals(e), a = 0;
	if (i.length == 0) {
		for (let n = 0; n < e.childCount; n++) {
			let o = e.child(n);
			r(o, i, t.forChild(a, o), n), a += o.nodeSize;
		}
		return;
	}
	let o = 0, s = [], c = null;
	for (let l = 0;;) {
		let u, d;
		for (; o < i.length && i[o].to == a;) {
			let e = i[o++];
			e.widget && (u ? (d ||= [u]).push(e) : u = e);
		}
		if (u) {
			if (d) {
				d.sort(Ff);
				for (let e = 0; e < d.length; e++) n(d[e], l, !!c);
			} else n(u, l, !!c);
		}
		let f, p;
		if (c) p = -1, f = c, c = null;
		else if (l < e.childCount) p = l, f = e.child(l++);
		else break;
		for (let e = 0; e < s.length; e++) s[e].to <= a && s.splice(e--, 1);
		for (; o < i.length && i[o].from <= a && i[o].to > a;) s.push(i[o++]);
		let m = a + f.nodeSize;
		if (f.isText) {
			let e = m;
			o < i.length && i[o].from < e && (e = i[o].from);
			for (let t = 0; t < s.length; t++) s[t].to < e && (e = s[t].to);
			e < m && (c = f.cut(e - a), f = f.cut(0, e - a), m = e, p = -1);
		} else for (; o < i.length && i[o].to < m;) o++;
		let h = f.isInline && !f.isLeaf ? s.filter((e) => !e.inline) : s.slice();
		r(f, h, t.forChild(a, f), p), a = m;
	}
}
function Lf(e) {
	if (e.nodeName == "UL" || e.nodeName == "OL") {
		let t = e.style.cssText;
		e.style.cssText = t + "; list-style: square !important", window.getComputedStyle(e).listStyle, e.style.cssText = t;
	}
}
function Rf(e, t, n, r) {
	for (let i = 0, a = 0; i < e.childCount && a <= r;) {
		let o = e.child(i++), s = a;
		if (a += o.nodeSize, !o.isText) continue;
		let c = o.text;
		for (; i < e.childCount;) {
			let t = e.child(i++);
			if (a += t.nodeSize, !t.isText) break;
			c += t.text;
		}
		if (a >= n) {
			if (a >= r && c.slice(r - t.length - s, r - s) == t) return r - t.length;
			let e = s < r ? c.lastIndexOf(t, r - s - 1) : -1;
			if (e >= 0 && e + t.length + s >= n) return s + e;
			if (n == r && c.length >= r + t.length - s && c.slice(r - s, r - s + t.length) == t) return r;
		}
	}
	return -1;
}
function zf(e, t, n, r, i) {
	let a = [];
	for (let o = 0, s = 0; o < e.length; o++) {
		let c = e[o], l = s, u = s += c.size;
		l >= n || u <= t ? a.push(c) : (l < t && a.push(c.slice(0, t - l, r)), i &&= (a.push(i), void 0), u > n && a.push(c.slice(n - l, c.size, r)));
	}
	return a;
}
function Bf(e, t = null) {
	let n = e.domSelectionRange(), r = e.state.doc;
	if (!n.focusNode) return null;
	let i = e.docView.nearestDesc(n.focusNode), a = i && i.size == 0, o = e.docView.posFromDOM(n.focusNode, n.focusOffset, 1);
	if (o < 0) return null;
	let s = r.resolve(o), c, l;
	if (fd(n)) {
		for (c = o; i && !i.node;) i = i.parent;
		if (i && i.node.isAtom && U.isSelectable(i.node) && i.parent && !(i.node.isInline && ud(n.focusNode, n.focusOffset, i.dom))) {
			let e = i.posBefore;
			l = new U(o == e ? s : r.resolve(e));
		} else if (Cd && t == "key" && o > e.state.selection.to && K(n.focusNode) && n.focusOffset == n.focusNode.childNodes.length && i && i.posAtStart > e.state.selection.to) for (let e = n.focusNode;; e = e.parentNode) {
			if (getComputedStyle(e).overflow != "visible") {
				l = V.findFrom(r.resolve(i.posAtStart), 1);
				break;
			}
			if (e == i.dom || e.nextSibling || !e.parentNode || !K(e.parentNode)) break;
		}
	} else {
		if (n instanceof e.dom.ownerDocument.defaultView.Selection && n.rangeCount > 1) {
			let t = o, i = o;
			for (let r = 0; r < n.rangeCount; r++) {
				let a = n.getRangeAt(r);
				t = Math.min(t, e.docView.posFromDOM(a.startContainer, a.startOffset, 1)), i = Math.max(i, e.docView.posFromDOM(a.endContainer, a.endOffset, -1));
			}
			if (t < 0) return null;
			[c, o] = i == e.state.selection.anchor ? [i, t] : [t, i], s = r.resolve(o);
		} else c = e.docView.posFromDOM(n.anchorNode, n.anchorOffset, 1);
		if (c < 0) return null;
	}
	let u = r.resolve(c);
	if (!l) {
		let n = t == "pointer" || e.state.selection.head < s.pos && !a ? 1 : -1;
		l = Zf(e, u, s, n);
	}
	return l;
}
function Vf(e) {
	return e.editable ? e.hasFocus() : $f(e) && document.activeElement && document.activeElement.contains(e.dom);
}
function Hf(e, t = !1) {
	let n = e.state.selection;
	if (Yf(e, n), !Vf(e)) return;
	let r = e.input.mouseDown;
	if (!t && Y && r) {
		let t = e.domSelectionRange(), n = e.domObserver.currentSelection;
		if (t.anchorNode && n.anchorNode && id(t.anchorNode, t.anchorOffset, n.anchorNode, n.anchorOffset) && r.delaySelUpdate()) {
			e.domObserver.setCurSelection();
			return;
		}
	}
	if (e.domObserver.disconnectSelection(), e.cursorWrapper) Jf(e);
	else {
		let { anchor: r, head: i } = n, a, o;
		Uf && !(n instanceof H) && (n.$from.parent.inlineContent || (a = Wf(e, n.from)), !n.empty && !n.$from.parent.inlineContent && (o = Wf(e, n.to))), e.docView.setSelection(r, i, e, t), Uf && (a && Kf(a), o && Kf(o)), n.visible ? e.dom.classList.remove("ProseMirror-hideselection") : (e.dom.classList.add("ProseMirror-hideselection"), "onselectionchange" in document && qf(e));
	}
	e.domObserver.setCurSelection(), e.domObserver.connectSelection();
}
var Uf = X || Y && Td < 63;
function Wf(e, t) {
	let { node: n, offset: r } = e.docView.domFromPos(t, 0), i = r < n.childNodes.length ? n.childNodes[r] : null, a = r ? n.childNodes[r - 1] : null;
	if (X && i && i.contentEditable == "false") return Gf(i);
	if ((!i || i.contentEditable == "false") && (!a || a.contentEditable == "false")) {
		if (i) return Gf(i);
		if (a) return Gf(a);
	}
}
function Gf(e) {
	return e.contentEditable = "true", X && e.draggable && (e.draggable = !1, e.wasDraggable = !0), e;
}
function Kf(e) {
	e.contentEditable = "false", e.wasDraggable &&= (e.draggable = !0, null);
}
function qf(e) {
	let t = e.dom.ownerDocument;
	t.removeEventListener("selectionchange", e.input.hideSelectionGuard);
	let n = e.domSelectionRange(), r = n.anchorNode, i = n.anchorOffset;
	t.addEventListener("selectionchange", e.input.hideSelectionGuard = () => {
		(n.anchorNode != r || n.anchorOffset != i) && (t.removeEventListener("selectionchange", e.input.hideSelectionGuard), setTimeout(() => {
			(!Vf(e) || e.state.selection.visible) && e.dom.classList.remove("ProseMirror-hideselection");
		}, 20));
	});
}
function Jf(e) {
	let t = e.domSelection();
	if (!t) return;
	let n = e.cursorWrapper.dom, r = n.nodeName == "IMG";
	r ? t.collapse(n.parentNode, W(n) + 1) : t.collapse(n, 0), !r && !e.state.selection.visible && J && Sd <= 11 && (n.disabled = !0, n.disabled = !1);
}
function Yf(e, t) {
	if (t instanceof U) {
		let n = e.docView.descAt(t.from);
		n != e.lastSelectedViewDesc && (Xf(e), n && n.selectNode(), e.lastSelectedViewDesc = n);
	} else Xf(e);
}
function Xf(e) {
	e.lastSelectedViewDesc &&= (e.lastSelectedViewDesc.parent && e.lastSelectedViewDesc.deselectNode(), void 0);
}
function Zf(e, t, n, r) {
	return e.someProp("createSelectionBetween", (r) => r(e, t, n)) || H.between(t, n, r);
}
function Qf(e) {
	return e.editable && !e.hasFocus() ? !1 : $f(e);
}
function $f(e) {
	let t = e.domSelectionRange();
	if (!t.anchorNode) return !1;
	try {
		return sd(e.dom, t.anchorNode) && (e.editable || sd(e.dom, t.focusNode));
	} catch {
		return !1;
	}
}
function ep(e) {
	let t = e.docView.domFromPos(e.state.selection.anchor, 0), n = e.domSelectionRange();
	return id(t.node, t.offset, n.anchorNode, n.anchorOffset);
}
function tp(e, t) {
	let { $anchor: n, $head: r } = e.selection, i = t > 0 ? n.max(r) : n.min(r), a = i.parent.inlineContent ? i.depth ? e.doc.resolve(t > 0 ? i.after() : i.before()) : null : i;
	return a && V.findFrom(a, t);
}
function np(e, t) {
	return e.dispatch(e.state.tr.setSelection(t).scrollIntoView()), !0;
}
function rp(e, t, n) {
	let r = e.state.selection;
	if (r instanceof H) {
		if (n.indexOf("s") > -1) {
			let { $head: n } = r, i = n.textOffset ? null : t < 0 ? n.nodeBefore : n.nodeAfter;
			if (!i || i.isText || !i.isLeaf) return !1;
			let a = e.state.doc.resolve(n.pos + i.nodeSize * (t < 0 ? -1 : 1));
			return np(e, new H(r.$anchor, a));
		}
		if (!r.empty) return !1;
		if (e.endOfTextblock(t > 0 ? "forward" : "backward")) {
			let n = tp(e.state, t);
			return n && n instanceof U ? np(e, n) : !1;
		}
		if (!Dd || n.indexOf("m") < 0) {
			let n = r.$head, i = n.textOffset ? null : t < 0 ? n.nodeBefore : n.nodeAfter, a;
			if (!i || i.isText) return !1;
			let o = t < 0 ? n.pos - i.nodeSize : n.pos;
			return i.isAtom || (a = e.docView.descAt(o)) && !a.contentDOM ? U.isSelectable(i) ? np(e, new U(t < 0 ? e.state.doc.resolve(n.pos - i.nodeSize) : n)) : np(e, new H(e.state.doc.resolve(t < 0 ? o : o + i.nodeSize))) : !1;
		}
	} else if (r instanceof U && r.node.isInline) return np(e, new H(t > 0 ? r.$to : r.$from));
	else {
		let n = tp(e.state, t);
		return n ? np(e, n) : !1;
	}
}
function ip(e, t) {
	let n = e.pmViewDesc;
	return n ? n.size == 0 && (t < 0 || e.nextSibling || e.nodeName != "BR") : K(e) && e.contentEditable == "false";
}
function ap(e, t) {
	return t < 0 ? op(e) : sp(e);
}
function op(e) {
	let t = e.domSelectionRange(), n = t.focusNode, r = t.focusOffset;
	if (!n) return;
	let i, a, o = !1;
	for (Cd && K(n) && r < G(n) && ip(n.childNodes[r], -1) && (o = !0);;) if (r > 0) {
		if (!K(n)) break;
		let e = n.childNodes[r - 1];
		if (ip(e, -1)) i = n, a = --r;
		else if (q(e)) n = e, r = n.nodeValue.length;
		else break;
	} else if (cp(n)) break;
	else {
		let t = n.previousSibling;
		for (; t && ip(t, -1);) i = n.parentNode, a = W(t), t = t.previousSibling;
		if (t) n = t, r = G(n);
		else {
			if (n = n.parentNode, n == e.dom) break;
			r = 0;
		}
	}
	o ? dp(e, n, r) : i && dp(e, i, a);
}
function sp(e) {
	let t = e.domSelectionRange(), n = t.focusNode, r = t.focusOffset;
	if (!n) return;
	let i = G(n), a, o;
	for (;;) if (r < i) {
		if (!K(n)) break;
		let e = n.childNodes[r];
		if (ip(e, 1)) a = n, o = ++r;
		else break;
	} else if (cp(n)) break;
	else {
		let t = n.nextSibling;
		for (; t && ip(t, 1);) a = t.parentNode, o = W(t) + 1, t = t.nextSibling;
		if (t) n = t, r = 0, i = G(n);
		else {
			if (n = n.parentNode, n == e.dom) break;
			r = i = 0;
		}
	}
	a && dp(e, a, o);
}
function cp(e) {
	let t = e.pmViewDesc;
	return t && t.node && t.node.isBlock;
}
function lp(e, t) {
	for (; e && t == e.childNodes.length && !dd(e);) t = W(e) + 1, e = e.parentNode;
	for (; e && t < e.childNodes.length;) {
		let n = e.childNodes[t];
		if (q(n)) return n;
		if (K(n) && n.contentEditable == "false") break;
		e = n, t = 0;
	}
}
function up(e, t) {
	for (; e && !t && !dd(e);) t = W(e), e = e.parentNode;
	for (; e && t;) {
		let n = e.childNodes[t - 1];
		if (q(n)) return n;
		if (K(n) && n.contentEditable == "false") break;
		e = n, t = e.childNodes.length;
	}
}
function dp(e, t, n) {
	if (!q(t)) {
		let e, r;
		(r = lp(t, n)) ? (t = r, n = 0) : (e = up(t, n)) && (t = e, n = e.nodeValue.length);
	}
	let r = e.domSelection(), i = e.domSelectionRange();
	if (!r) return;
	fd(i) ? r.collapse(t, n) : r.extend && (r.collapse(i.anchorNode, i.anchorOffset), r.extend(t, n)), e.domObserver.setCurSelection();
	let { state: a } = e;
	setTimeout(() => {
		e.state == a && Hf(e);
	}, 50);
}
function fp(e, t) {
	let n = e.state.doc.resolve(t);
	if (!(Y || Od) && n.parent.inlineContent) {
		let r = e.coordsAtPos(t);
		if (t > n.start()) {
			let n = e.coordsAtPos(t - 1), i = (n.top + n.bottom) / 2;
			if (i > r.top && i < r.bottom && Math.abs(n.left - r.left) > 1) return n.left < r.left ? "ltr" : "rtl";
		}
		if (t < n.end()) {
			let n = e.coordsAtPos(t + 1), i = (n.top + n.bottom) / 2;
			if (i > r.top && i < r.bottom && Math.abs(n.left - r.left) > 1) return n.left > r.left ? "ltr" : "rtl";
		}
	}
	let r = e.dom;
	for (let t = n.depth; t > 0; t--) if (n.node(t).isBlock) {
		r = e.nodeDOM(n.before(t)) || r;
		break;
	}
	return getComputedStyle(r).direction == "rtl" ? "rtl" : "ltr";
}
function pp(e, t, n) {
	let r = e.state.selection;
	if (r instanceof H && !r.empty || n.indexOf("s") > -1 || Dd && n.indexOf("m") > -1) return !1;
	let { $from: i, $to: a } = r;
	if (!i.parent.inlineContent || e.endOfTextblock(t < 0 ? "up" : "down")) {
		let n = tp(e.state, t);
		if (n && n instanceof U) return np(e, n);
	}
	if (!i.parent.inlineContent) {
		let n = t < 0 ? i : a, o = r instanceof Bc ? V.near(n, t) : V.findFrom(n, t);
		return o ? np(e, o) : !1;
	}
	return !1;
}
function mp(e, t) {
	if (!(e.state.selection instanceof H)) return !0;
	let { $head: n, $anchor: r, empty: i } = e.state.selection;
	if (!n.sameParent(r)) return !0;
	if (!i) return !1;
	if (e.endOfTextblock(t > 0 ? "forward" : "backward")) return !0;
	let a = !n.textOffset && (t < 0 ? n.nodeBefore : n.nodeAfter);
	if (a && !a.isText) {
		let r = e.state.tr;
		return t < 0 ? r.delete(n.pos - a.nodeSize, n.pos) : r.delete(n.pos, n.pos + a.nodeSize), e.dispatch(r), !0;
	}
	return !1;
}
function hp(e, t, n) {
	e.domObserver.stop(), t.contentEditable = n, e.domObserver.start();
}
function gp(e) {
	if (!X || e.state.selection.$head.parentOffset > 0) return !1;
	let { focusNode: t, focusOffset: n } = e.domSelectionRange();
	if (t && K(t) && n == 0 && t.firstChild && K(t.firstChild) && t.firstChild.contentEditable == "false") {
		let n = t.firstChild;
		hp(e, n, "true"), setTimeout(() => hp(e, n, "false"), 20);
	}
	return !1;
}
function _p(e) {
	let t = "";
	return e.ctrlKey && (t += "c"), e.metaKey && (t += "m"), e.altKey && (t += "a"), e.shiftKey && (t += "s"), t;
}
function vp(e, t) {
	let n = t.keyCode, r = _p(t);
	if (n == 8 || Dd && n == 72 && r == "c") return mp(e, -1) || ap(e, -1);
	if (n == 46 && !t.shiftKey || Dd && n == 68 && r == "c") return mp(e, 1) || ap(e, 1);
	if (n == 13 || n == 27) return !0;
	if (n == 37 || Dd && n == 66 && r == "c") {
		let t = n == 37 ? fp(e, e.state.selection.from) == "ltr" ? -1 : 1 : -1;
		return rp(e, t, r) || ap(e, t);
	}
	if (n == 39 || Dd && n == 70 && r == "c") {
		let t = n == 39 ? fp(e, e.state.selection.from) == "ltr" ? 1 : -1 : 1;
		return rp(e, t, r) || ap(e, t);
	}
	return n == 38 || Dd && n == 80 && r == "c" ? pp(e, -1, r) || ap(e, -1) : n == 40 || Dd && n == 78 && r == "c" ? gp(e) || pp(e, 1, r) || ap(e, 1) : !(r != (Dd ? "m" : "c") || n != 66 && n != 73 && n != 89 && n != 90);
}
function yp(e, t) {
	e.someProp("transformCopied", (n) => {
		t = n(t, e);
	});
	let n = [], { content: r, openStart: i, openEnd: a } = t;
	for (; i > 1 && a > 1 && r.childCount == 1 && r.firstChild.childCount == 1;) {
		i--, a--;
		let e = r.firstChild;
		n.push(e.type.name, e.attrs == e.type.defaultAttrs ? null : e.attrs), r = e.content;
	}
	let o = e.someProp("clipboardSerializer") || ms.fromSchema(e.state.schema), s = kp(), c = s.createElement("div");
	c.appendChild(o.serializeFragment(r, { document: s }));
	let l = c.firstChild, u, d = 0;
	for (; l && K(l) && (u = Op[l.nodeName.toLowerCase()]);) {
		for (let e = u.length - 1; e >= 0; e--) {
			let t = s.createElement(u[e]);
			for (; c.firstChild;) t.appendChild(c.firstChild);
			c.appendChild(t), d++;
		}
		l = c.firstChild;
	}
	return l && K(l) && l.setAttribute("data-pm-slice", `${i} ${a}${d ? ` -${d}` : ""} ${JSON.stringify(n)}`), {
		dom: c,
		text: e.someProp("clipboardTextSerializer", (n) => n(t, e)) || t.content.textBetween(0, t.content.size, "\n\n"),
		slice: t
	};
}
function bp(e, t, n, r, i) {
	let a = i.parent.type.spec.code, o, s;
	if (!n && !t) return null;
	let c = !!t && (r || a || !n);
	if (c) {
		if (e.someProp("transformPastedText", (n) => {
			t = n(t, a || r, e);
		}), a) return s = new R(I.from(e.state.schema.text(t.replace(/\r\n?/g, "\n"))), 0, 0), e.someProp("transformPasted", (t) => {
			s = t(s, e, !0);
		}), s;
		let n = e.someProp("clipboardTextParser", (n) => n(t, i, r, e));
		if (n) s = n;
		else {
			let n = i.marks(), { schema: r } = e.state, a = ms.fromSchema(r);
			o = document.createElement("div"), t.split(/(?:\r\n?|\n)+/).forEach((e) => {
				let t = o.appendChild(document.createElement("p"));
				e && t.appendChild(a.serializeNode(r.text(e, n)));
			});
		}
	} else e.someProp("transformPastedHTML", (t) => {
		n = t(n, e);
	}), o = Mp(n), Ad && Np(o);
	let l = o && o.querySelector("[data-pm-slice]"), u = l && /^(\d+) (\d+)(?: -(\d+))? (.*)/.exec(l.getAttribute("data-pm-slice") || "");
	if (u && u[3]) for (let e = +u[3]; e > 0; e--) {
		let e = o.firstChild;
		for (; e && !K(e);) e = e.nextSibling;
		if (!e) break;
		o = e;
	}
	if (s ||= (e.someProp("clipboardParser") || e.someProp("domParser") || $o.fromSchema(e.state.schema)).parseSlice(o, {
		preserveWhitespace: !!(c || u),
		context: i,
		ruleFromNode(e) {
			return e.nodeName == "BR" && !e.nextSibling && e.parentNode && !xp.test(e.parentNode.nodeName) ? { ignore: !0 } : null;
		}
	}), u) s = Pp(Dp(s, +u[1], +u[2]), u[4]);
	else if (s = R.maxOpen(Sp(s.content, i), !0), s.openStart || s.openEnd) {
		let e = 0, t = 0;
		for (let t = s.content.firstChild; e < s.openStart && !t.type.spec.isolating; e++, t = t.firstChild);
		for (let e = s.content.lastChild; t < s.openEnd && !e.type.spec.isolating; t++, e = e.lastChild);
		s = Dp(s, e, t);
	}
	return e.someProp("transformPasted", (t) => {
		s = t(s, e, c);
	}), s;
}
var xp = /^(a|abbr|acronym|b|cite|code|del|em|i|ins|kbd|label|output|q|ruby|s|samp|span|strong|sub|sup|time|u|tt|var)$/i;
function Sp(e, t) {
	if (e.childCount < 2) return e;
	for (let n = t.depth; n >= 0; n--) {
		let r = t.node(n).contentMatchAt(t.index(n)), i, a = [];
		if (e.forEach((e) => {
			if (!a) return;
			let t = r.findWrapping(e.type), n;
			if (!t) return a = null;
			if (n = a.length && i.length && wp(t, i, e, a[a.length - 1], 0)) a[a.length - 1] = n;
			else {
				a.length && (a[a.length - 1] = Tp(a[a.length - 1], i.length));
				let n = Cp(e, t);
				a.push(n), r = r.matchType(n.type), i = t;
			}
		}), a) return I.from(a);
	}
	return e;
}
function Cp(e, t, n = 0) {
	for (let r = t.length - 1; r >= n; r--) e = t[r].create(null, I.from(e));
	return e;
}
function wp(e, t, n, r, i) {
	if (i < e.length && i < t.length && e[i] == t[i]) {
		let a = wp(e, t, n, r.lastChild, i + 1);
		if (a) return r.copy(r.content.replaceChild(r.childCount - 1, a));
		if (r.contentMatchAt(r.childCount).matchType(i == e.length - 1 ? n.type : e[i + 1])) return r.copy(r.content.append(I.from(Cp(n, e, i + 1))));
	}
}
function Tp(e, t) {
	if (t == 0) return e;
	let n = e.content.replaceChild(e.childCount - 1, Tp(e.lastChild, t - 1)), r = e.contentMatchAt(e.childCount).fillBefore(I.empty, !0);
	return e.copy(n.append(r));
}
function Ep(e, t, n, r, i, a) {
	let o = t < 0 ? e.firstChild : e.lastChild, s = o.content;
	return e.childCount > 1 && (a = 0), i < r - 1 && (s = Ep(s, t, n, r, i + 1, a)), i >= n && (s = t < 0 ? o.contentMatchAt(0).fillBefore(s, a <= i).append(s) : s.append(o.contentMatchAt(o.childCount).fillBefore(I.empty, !0))), e.replaceChild(t < 0 ? 0 : e.childCount - 1, o.copy(s));
}
function Dp(e, t, n) {
	return t < e.openStart && (e = new R(Ep(e.content, -1, t, e.openStart, 0, e.openEnd), t, e.openEnd)), n < e.openEnd && (e = new R(Ep(e.content, 1, n, e.openEnd, 0, 0), e.openStart, n)), e;
}
var Op = {
	thead: ["table"],
	tbody: ["table"],
	tfoot: ["table"],
	caption: ["table"],
	colgroup: ["table"],
	col: ["table", "colgroup"],
	tr: ["table", "tbody"],
	td: [
		"table",
		"tbody",
		"tr"
	],
	th: [
		"table",
		"tbody",
		"tr"
	]
};
function kp() {
	return document.implementation.createHTMLDocument("title");
}
var Ap = null;
function jp(e) {
	let t = window.trustedTypes;
	if (!t) return e;
	if (!Ap) {
		if (Ap = t.defaultPolicy) try {
			return Ap.createHTML(e);
		} catch {}
		Ap = t.createPolicy("ProseMirrorClipboard", { createHTML: (e) => e });
	}
	return Ap.createHTML(e);
}
function Mp(e) {
	let t = /^(\s*<meta [^>]*>)*/.exec(e);
	t && (e = e.slice(t[0].length));
	let n = kp(), r = n.body, i = /<([a-z][^>\s]+)/i.exec(e), a;
	if ((a = i && Op[i[1].toLowerCase()]) && (e = a.map((e) => "<" + e + ">").join("") + e + a.map((e) => "</" + e + ">").reverse().join("")), r.innerHTML = jp(e), a) for (let e = 0; e < a.length; e++) r = r.querySelector(a[e]) || r;
	for (let e = 0; e < n.styleSheets.length; e++) {
		let t = n.styleSheets[e];
		for (let e = 0; e < t.rules.length; e++) {
			let n = t.rules[e];
			if (n instanceof CSSStyleRule) {
				let e = r.querySelectorAll(n.selectorText);
				for (let t = 0; t < e.length; t++) e[t].style.cssText += n.style.cssText;
			}
		}
	}
	return r;
}
function Np(e) {
	let t = e.querySelectorAll(Y ? "span:not([class]):not([style])" : "span.Apple-converted-space");
	for (let n = 0; n < t.length; n++) {
		let r = t[n];
		r.childNodes.length == 1 && r.textContent == "\xA0" && r.parentNode && r.parentNode.replaceChild(e.ownerDocument.createTextNode(" "), r);
	}
}
function Pp(e, t) {
	if (!e.size) return e;
	let n = e.content.firstChild.type.schema, r;
	try {
		r = JSON.parse(t);
	} catch {
		return e;
	}
	let { content: i, openStart: a, openEnd: o } = e;
	for (let e = r.length - 2; e >= 0; e -= 2) {
		let t = n.nodes[r[e]];
		if (!t || t.hasRequiredAttrs()) break;
		try {
			i = I.from(t.create(r[e + 1], i));
		} catch {
			break;
		}
		a++, o++;
	}
	return new R(i, a, o);
}
var Z = {}, Q = {}, Fp = {
	touchstart: !0,
	touchmove: !0
}, Ip = class {
	constructor() {
		this.shiftKey = !1, this.mouseDown = null, this.lastKeyCode = null, this.lastKeyCodeTime = 0, this.lastClick = {
			time: 0,
			x: 0,
			y: 0,
			type: "",
			button: 0
		}, this.lastSelectionOrigin = null, this.lastSelectionTime = 0, this.lastIOSEnter = 0, this.lastIOSEnterFallbackTimeout = -1, this.lastFocus = 0, this.lastTouch = 0, this.lastChromeDelete = 0, this.composing = !1, this.compositionNode = null, this.composingTimeout = -1, this.compositionNodes = [], this.compositionEndedAt = -2e8, this.compositionID = 1, this.badSafariComposition = !1, this.compositionPendingChanges = 0, this.domChangeCount = 0, this.eventHandlers = Object.create(null), this.hideSelectionGuard = null;
	}
};
function Lp(e) {
	for (let t in Z) {
		let n = Z[t];
		e.dom.addEventListener(t, e.input.eventHandlers[t] = (t) => {
			Hp(e, t) && !Vp(e, t) && (e.editable || !(t.type in Q)) && n(e, t);
		}, Fp[t] ? { passive: !0 } : void 0);
	}
	X && e.dom.addEventListener("input", () => null), Bp(e);
}
function Rp(e, t) {
	e.input.lastSelectionOrigin = t, e.input.lastSelectionTime = Date.now();
}
function zp(e) {
	e.input.mouseDown && e.input.mouseDown.done(), e.domObserver.stop();
	for (let t in e.input.eventHandlers) e.dom.removeEventListener(t, e.input.eventHandlers[t]);
	clearTimeout(e.input.composingTimeout), clearTimeout(e.input.lastIOSEnterFallbackTimeout);
}
function Bp(e) {
	e.someProp("handleDOMEvents", (t) => {
		for (let n in t) e.input.eventHandlers[n] || e.dom.addEventListener(n, e.input.eventHandlers[n] = (t) => Vp(e, t));
	});
}
function Vp(e, t) {
	return e.someProp("handleDOMEvents", (n) => {
		let r = n[t.type];
		return r ? r(e, t) || t.defaultPrevented : !1;
	});
}
function Hp(e, t) {
	if (!t.bubbles) return !0;
	if (t.defaultPrevented) return !1;
	for (let n = t.target; n != e.dom; n = n.parentNode) if (!n || n.nodeType == 11 || n.pmViewDesc && n.pmViewDesc.stopEvent(t)) return !1;
	return !0;
}
function Up(e, t) {
	!Vp(e, t) && Z[t.type] && (e.editable || !(t.type in Q)) && Z[t.type](e, t);
}
Q.keydown = (e, t) => {
	let n = t;
	if (e.input.shiftKey = n.keyCode == 16 || n.shiftKey, !om(e) && (e.input.lastKeyCode = n.keyCode, e.input.lastKeyCodeTime = Date.now(), !(kd && Y && n.keyCode == 13))) {
		if (n.keyCode != 229 && e.domObserver.forceFlush(), Ed && n.keyCode == 13 && !n.ctrlKey && !n.altKey && !n.metaKey) {
			let t = Date.now();
			e.input.lastIOSEnter = t, e.input.lastIOSEnterFallbackTimeout = setTimeout(() => {
				e.input.lastIOSEnter == t && (e.someProp("handleKeyDown", (t) => t(e, pd(13, "Enter"))), e.input.lastIOSEnter = 0);
			}, 200);
		} else e.someProp("handleKeyDown", (t) => t(e, n)) || vp(e, n) ? n.preventDefault() : Rp(e, "key");
	}
}, Q.keyup = (e, t) => {
	t.keyCode == 16 && (e.input.shiftKey = !1);
}, Q.keypress = (e, t) => {
	let n = t;
	if (om(e) || !n.charCode || n.ctrlKey && !n.altKey || Dd && n.metaKey) return;
	if (e.someProp("handleKeyPress", (t) => t(e, n))) {
		n.preventDefault();
		return;
	}
	let r = e.state.selection;
	if (!(r instanceof H) || !r.$from.sameParent(r.$to)) {
		let t = String.fromCharCode(n.charCode), i = () => e.state.tr.insertText(t).scrollIntoView();
		!/[\r\n]/.test(t) && !e.someProp("handleTextInput", (n) => n(e, r.$from.pos, r.$to.pos, t, i)) && e.dispatch(i()), n.preventDefault();
	}
};
function Wp(e) {
	return {
		left: e.clientX,
		top: e.clientY
	};
}
function Gp(e, t) {
	let n = t.x - e.clientX, r = t.y - e.clientY;
	return n * n + r * r < 100;
}
function Kp(e, t, n, r, i) {
	if (r == -1) return !1;
	let a = e.state.doc.resolve(r);
	for (let r = a.depth + 1; r > 0; r--) if (e.someProp(t, (t) => r > a.depth ? t(e, n, a.nodeAfter, a.before(r), i, !0) : t(e, n, a.node(r), a.before(r), i, !1))) return !0;
	return !1;
}
function qp(e, t, n) {
	if (e.focused || e.focus(), e.state.selection.eq(t)) return;
	let r = e.state.tr.setSelection(t);
	r.setMeta("pointer", !0), e.dispatch(r);
}
function Jp(e, t) {
	if (t == -1) return !1;
	let n = e.state.doc.resolve(t), r = n.nodeAfter;
	return r && r.isAtom && U.isSelectable(r) ? (qp(e, new U(n)), !0) : !1;
}
function Yp(e, t) {
	if (t == -1) return !1;
	let n = e.state.selection, r, i;
	n instanceof U && (r = n.node);
	let a = e.state.doc.resolve(t);
	for (let e = a.depth + 1; e > 0; e--) {
		let t = e > a.depth ? a.nodeAfter : a.node(e);
		if (U.isSelectable(t)) {
			i = r && n.$from.depth > 0 && e >= n.$from.depth && a.before(n.$from.depth + 1) == n.$from.pos ? a.before(n.$from.depth) : a.before(e);
			break;
		}
	}
	return i != null && (qp(e, U.create(e.state.doc, i)), !0);
}
function Xp(e, t, n, r, i) {
	return Kp(e, "handleClickOn", t, n, r) || e.someProp("handleClick", (n) => n(e, t, r)) || (i ? Yp(e, n) : Jp(e, n));
}
function Zp(e, t, n, r) {
	return Kp(e, "handleDoubleClickOn", t, n, r) || e.someProp("handleDoubleClick", (n) => n(e, t, r));
}
function Qp(e, t, n, r) {
	return Kp(e, "handleTripleClickOn", t, n, r) || e.someProp("handleTripleClick", (n) => n(e, t, r)) || $p(e, n, r);
}
function $p(e, t, n) {
	if (n.button != 0) return !1;
	let r = em(e, t, !0), i = e.state.doc;
	return r ? (qp(e, r), r instanceof H && i.eq(e.state.doc) && (e.input.mouseDown = new am(e, r)), !0) : !1;
}
function em(e, t, n) {
	let r = e.state.doc;
	if (t == -1) return r.inlineContent ? H.create(r, 0, r.content.size) : null;
	let i = r.resolve(t);
	for (let e = i.depth + 1; e > 0; e--) {
		let t = e > i.depth ? i.nodeAfter : i.node(e), a = i.before(e);
		if (t.inlineContent) return H.create(r, a + 1, a + 1 + t.content.size);
		if (n && U.isSelectable(t)) return U.create(r, a);
	}
	return null;
}
function tm(e) {
	return fm(e);
}
var nm = Dd ? "metaKey" : "ctrlKey";
Z.mousedown = (e, t) => {
	let n = t;
	e.input.shiftKey = n.shiftKey;
	let r = tm(e), i = Date.now(), a = "singleClick";
	i - e.input.lastClick.time < 500 && Gp(n, e.input.lastClick) && !n[nm] && e.input.lastClick.button == n.button && (e.input.lastClick.type == "singleClick" ? a = "doubleClick" : e.input.lastClick.type == "doubleClick" && (a = "tripleClick")), e.input.lastClick = {
		time: i,
		x: n.clientX,
		y: n.clientY,
		type: a,
		button: n.button
	}, e.input.mouseDown && e.input.mouseDown.done();
	let o = e.posAtCoords(Wp(n));
	o && (a == "singleClick" ? e.input.mouseDown = new im(e, o, n, !!r) : (a == "doubleClick" ? Zp : Qp)(e, o.pos, o.inside, n) ? n.preventDefault() : Rp(e, "pointer"));
};
var rm = class {
	constructor(e) {
		this.view = e, this.mightDrag = null, e.root.addEventListener("mouseup", this.up = this.up.bind(this)), e.root.addEventListener("mousemove", this.move = this.move.bind(this));
	}
	up(e) {
		this.done();
	}
	move(e) {
		e.buttons == 0 && this.done();
	}
	done() {
		this.view.root.removeEventListener("mouseup", this.up), this.view.root.removeEventListener("mousemove", this.move), this.view.input.mouseDown == this && (this.view.input.mouseDown = null);
	}
	delaySelUpdate() {
		return !1;
	}
}, im = class extends rm {
	constructor(e, t, n, r) {
		super(e), this.pos = t, this.event = n, this.flushed = r, this.delayedSelectionSync = !1, this.startDoc = e.state.doc, this.selectNode = !!n[nm], this.allowDefault = n.shiftKey;
		let i, a;
		if (t.inside > -1) i = e.state.doc.nodeAt(t.inside), a = t.inside;
		else {
			let n = e.state.doc.resolve(t.pos);
			i = n.parent, a = n.depth ? n.before() : 0;
		}
		let o = r ? null : n.target, s = o ? e.docView.nearestDesc(o, !0) : null;
		this.target = s && K(s.nodeDOM) ? s.nodeDOM : null;
		let { selection: c } = e.state;
		n.button == 0 && (i.type.spec.draggable && i.type.spec.selectable !== !1 || c instanceof U && c.from <= a && c.to > a) && (this.mightDrag = {
			node: i,
			pos: a,
			addAttr: !(!this.target || this.target.draggable),
			setUneditable: !!(this.target && Cd && !this.target.hasAttribute("contentEditable"))
		}), this.target && this.mightDrag && (this.mightDrag.addAttr || this.mightDrag.setUneditable) && (this.view.domObserver.stop(), this.mightDrag.addAttr && (this.target.draggable = !0), this.mightDrag.setUneditable && setTimeout(() => {
			this.view.input.mouseDown == this && this.target.setAttribute("contentEditable", "false");
		}, 20), this.view.domObserver.start()), Rp(e, "pointer");
	}
	done() {
		super.done(), this.mightDrag && this.target && (this.view.domObserver.stop(), this.mightDrag.addAttr && this.target.removeAttribute("draggable"), this.mightDrag.setUneditable && this.target.removeAttribute("contentEditable"), this.view.domObserver.start()), this.delayedSelectionSync && setTimeout(() => {
			this.view.isDestroyed || Hf(this.view);
		});
	}
	up(e) {
		if (this.done(), !this.view.dom.contains(e.target)) return;
		let t = this.pos;
		this.view.state.doc != this.startDoc && (t = this.view.posAtCoords(Wp(e))), this.updateAllowDefault(e), this.allowDefault || !t ? Rp(this.view, "pointer") : Xp(this.view, t.pos, t.inside, e, this.selectNode) ? e.preventDefault() : e.button == 0 && (this.flushed || X && this.mightDrag && !this.mightDrag.node.isAtom || Y && !this.view.state.selection.visible && Math.min(Math.abs(t.pos - this.view.state.selection.from), Math.abs(t.pos - this.view.state.selection.to)) <= 2) ? (qp(this.view, V.near(this.view.state.doc.resolve(t.pos))), e.preventDefault()) : Rp(this.view, "pointer");
	}
	move(e) {
		this.updateAllowDefault(e), Rp(this.view, "pointer"), super.move(e);
	}
	updateAllowDefault(e) {
		!this.allowDefault && (Math.abs(this.event.x - e.clientX) > 4 || Math.abs(this.event.y - e.clientY) > 4) && (this.allowDefault = !0);
	}
	delaySelUpdate() {
		return this.allowDefault ? (this.delayedSelectionSync = !0, !0) : !1;
	}
}, am = class extends rm {
	constructor(e, t) {
		super(e), this.startSelection = t, this.startDoc = e.state.doc;
	}
	move(e) {
		if (e.buttons == 0 || this.view.isDestroyed || !this.view.state.doc.eq(this.startDoc)) {
			this.done();
			return;
		}
		e.preventDefault(), Rp(this.view, "pointer");
		let t = this.view.posAtCoords(Wp(e)), n = t && em(this.view, t.inside, !1);
		if (!n) return;
		let { doc: r } = this.view.state, i = this.startSelection, [a, o] = n.from < i.from ? [i.to, n.from] : [i.from, n.to];
		qp(this.view, H.create(r, a, o));
	}
};
Z.touchstart = (e) => {
	e.input.lastTouch = Date.now(), tm(e), Rp(e, "pointer");
}, Z.touchmove = (e) => {
	e.input.lastTouch = Date.now(), Rp(e, "pointer");
}, Z.contextmenu = (e) => tm(e);
function om(e, t) {
	return e.composing ? !0 : X && Math.abs(Date.now() - e.input.compositionEndedAt) < 500 ? (e.input.compositionEndedAt = -2e8, !0) : !1;
}
var sm = kd ? 5e3 : -1;
Q.compositionstart = Q.compositionupdate = (e) => {
	if (!e.composing) {
		e.domObserver.flush();
		let { state: t } = e, n = t.selection.$to;
		if (t.selection instanceof H && t.selection.empty && (t.storedMarks || !n.textOffset && n.parentOffset && n.nodeBefore.marks.some((e) => e.type.spec.inclusive === !1) || Y && Od && cm(e))) e.markCursor = e.state.storedMarks || n.marks(), fm(e, !0), e.markCursor = null;
		else if (fm(e, !t.selection.empty), Cd && t.selection.empty && n.parentOffset && !n.textOffset && n.nodeBefore.marks.length) {
			let t = e.domSelectionRange();
			for (let n = t.focusNode, r = t.focusOffset; n && K(n) && r != 0;) {
				let t = r < 0 ? n.lastChild : n.childNodes[r - 1];
				if (!t) break;
				if (q(t)) {
					let n = e.domSelection();
					n && n.collapse(t, t.nodeValue.length);
					break;
				}
				n = t, r = -1;
			}
		}
		e.input.composing = !0;
	}
	lm(e, sm);
};
function cm(e) {
	let { focusNode: t, focusOffset: n } = e.domSelectionRange();
	if (!t || !K(t) || n >= t.childNodes.length) return !1;
	let r = t.childNodes[n];
	return K(r) && r.contentEditable == "false";
}
Q.compositionend = (e, t) => {
	e.composing && (e.input.composing = !1, e.input.compositionEndedAt = Date.now(), e.input.compositionPendingChanges = e.domObserver.pendingRecords().length ? e.input.compositionID : 0, e.input.compositionNode = null, e.input.badSafariComposition ? e.domObserver.forceFlush() : e.input.compositionPendingChanges && Promise.resolve().then(() => e.domObserver.flush()), e.input.compositionID++, lm(e, 20));
};
function lm(e, t) {
	clearTimeout(e.input.composingTimeout), t > -1 && (e.input.composingTimeout = setTimeout(() => fm(e), t));
}
function um(e) {
	for (e.composing && (e.input.composing = !1, e.input.compositionEndedAt = Date.now()); e.input.compositionNodes.length > 0;) e.input.compositionNodes.pop().markParentsDirty();
}
function dm(e) {
	let t = e.domSelectionRange();
	if (!t.focusNode) return null;
	let n = cd(t.focusNode, t.focusOffset), r = ld(t.focusNode, t.focusOffset);
	if (n && r && n != r) {
		let t = r.pmViewDesc, i = e.domObserver.lastChangedTextNode;
		if (n == i || r == i) return i;
		if (!t || !t.isText(r.nodeValue)) return r;
		if (e.input.compositionNode == r) {
			let e = n.pmViewDesc;
			if (e && e.isText(n.nodeValue)) return r;
		}
	}
	return n || r;
}
function fm(e, t = !1) {
	if (!(kd && e.domObserver.flushingSoon >= 0)) {
		if (e.domObserver.forceFlush(), um(e), t || e.docView && e.docView.dirty) {
			let n = Bf(e), r = e.state.selection;
			return n && !n.eq(r) ? e.dispatch(e.state.tr.setSelection(n)) : (e.markCursor || t) && !r.$from.node(r.$from.sharedDepth(r.to)).inlineContent ? e.dispatch(e.state.tr.deleteSelection()) : e.updateState(e.state), !0;
		}
		return !1;
	}
}
function pm(e, t) {
	if (!e.dom.parentNode) return;
	let n = e.dom.parentNode.appendChild(document.createElement("div"));
	n.appendChild(t), n.style.cssText = "position: fixed; left: -10000px; top: 10px";
	let r = getSelection(), i = document.createRange();
	i.selectNodeContents(t), e.dom.blur(), r.removeAllRanges(), r.addRange(i), setTimeout(() => {
		n.parentNode && n.parentNode.removeChild(n), e.focus();
	}, 50);
}
var mm = J && Sd < 15 || Ed && jd < 604;
Z.copy = Q.cut = (e, t) => {
	let n = t, r = e.state.selection, i = n.type == "cut";
	if (r.empty) return;
	let a = mm ? null : n.clipboardData, { dom: o, text: s } = yp(e, r.content());
	a ? (n.preventDefault(), a.clearData(), a.setData("text/html", o.innerHTML), a.setData("text/plain", s)) : pm(e, o), i && e.dispatch(e.state.tr.deleteSelection().scrollIntoView().setMeta("uiEvent", "cut"));
};
function hm(e) {
	return e.openStart == 0 && e.openEnd == 0 && e.content.childCount == 1 ? e.content.firstChild : null;
}
function gm(e, t) {
	if (!e.dom.parentNode) return;
	let n = e.input.shiftKey || e.state.selection.$from.parent.type.spec.code, r = e.dom.parentNode.appendChild(document.createElement(n ? "textarea" : "div"));
	n || (r.contentEditable = "true"), r.style.cssText = "position: fixed; left: -10000px; top: 10px", r.focus();
	let i = e.input.shiftKey && e.input.lastKeyCode != 45;
	setTimeout(() => {
		e.focus(), r.parentNode && r.parentNode.removeChild(r), n ? _m(e, r.value, null, i, t) : _m(e, r.textContent, r.innerHTML, i, t);
	}, 50);
}
function _m(e, t, n, r, i) {
	let a = bp(e, t, n, r, e.state.selection.$from);
	if (e.someProp("handlePaste", (t) => t(e, i, a || R.empty))) return !0;
	if (!a) return !1;
	let o = hm(a), s = o ? e.state.tr.replaceSelectionWith(o, r) : e.state.tr.replaceSelection(a);
	return e.dispatch(s.scrollIntoView().setMeta("paste", !0).setMeta("uiEvent", "paste")), !0;
}
function vm(e) {
	let t = e.getData("text/plain") || e.getData("Text");
	if (t) return t;
	let n = e.getData("text/uri-list");
	return n ? n.replace(/\r?\n/g, " ") : "";
}
Q.paste = (e, t) => {
	let n = t;
	if (e.composing && !kd) return;
	let r = mm ? null : n.clipboardData, i = e.input.shiftKey && e.input.lastKeyCode != 45;
	r && _m(e, vm(r), r.getData("text/html"), i, n) ? n.preventDefault() : gm(e, n);
};
var ym = class {
	constructor(e, t, n) {
		this.slice = e, this.move = t, this.node = n;
	}
}, bm = Dd ? "altKey" : "ctrlKey";
function xm(e, t) {
	let n;
	return e.someProp("dragCopies", (e) => {
		n ||= e(t);
	}), n == null ? !t[bm] : !n;
}
Z.dragstart = (e, t) => {
	let n = t, r = e.input.mouseDown;
	if (r && r.done(), !n.dataTransfer) return;
	let i = e.state.selection, a = i.empty ? null : e.posAtCoords(Wp(n)), o;
	if (!(a && a.pos >= i.from && a.pos <= (i instanceof U ? i.to - 1 : i.to))) {
		if (r && r.mightDrag) o = U.create(e.state.doc, r.mightDrag.pos);
		else if (n.target && K(n.target)) {
			let t = e.docView.nearestDesc(n.target, !0);
			t && t.node.type.spec.draggable && t != e.docView && (o = U.create(e.state.doc, t.posBefore));
		}
	}
	let { dom: s, text: c, slice: l } = yp(e, (o || e.state.selection).content());
	(!n.dataTransfer.files.length || !Y || Td > 120) && n.dataTransfer.clearData(), n.dataTransfer.setData(mm ? "Text" : "text/html", s.innerHTML), n.dataTransfer.effectAllowed = "copyMove", mm || n.dataTransfer.setData("text/plain", c), e.dragging = new ym(l, xm(e, n), o);
}, Z.dragend = (e) => {
	let t = e.dragging;
	window.setTimeout(() => {
		e.dragging == t && (e.dragging = null);
	}, 50);
}, Q.dragover = Q.dragenter = (e, t) => t.preventDefault(), Q.drop = (e, t) => {
	try {
		Sm(e, t, e.dragging);
	} finally {
		e.dragging = null;
	}
};
function Sm(e, t, n) {
	if (!t.dataTransfer) return;
	let r = e.posAtCoords(Wp(t));
	if (!r) return;
	let i = e.state.doc.resolve(r.pos), a = n && n.slice;
	a ? e.someProp("transformPasted", (t) => {
		a = t(a, e, !1);
	}) : a = bp(e, vm(t.dataTransfer), mm ? null : t.dataTransfer.getData("text/html"), !1, i);
	let o = !!(n && xm(e, t));
	if (e.someProp("handleDrop", (n) => n(e, t, a || R.empty, o))) {
		t.preventDefault();
		return;
	}
	if (!a) return;
	t.preventDefault();
	let s = a ? pc(e.state.doc, i.pos, a) : i.pos;
	s ??= i.pos;
	let c = e.state.tr;
	if (o) {
		let { node: e } = n;
		e ? e.replace(c) : c.deleteSelection();
	}
	let l = c.mapping.map(s), u = a.openStart == 0 && a.openEnd == 0 && a.content.childCount == 1, d = c.doc;
	if (u ? c.replaceRangeWith(l, l, a.content.firstChild) : c.replaceRange(l, l, a), c.doc.eq(d)) return;
	let f = c.doc.resolve(l);
	if (u && U.isSelectable(a.content.firstChild) && f.nodeAfter && f.nodeAfter.sameMarkup(a.content.firstChild)) c.setSelection(new U(f));
	else {
		let t = c.mapping.map(s);
		c.mapping.maps[c.mapping.maps.length - 1].forEach((e, n, r, i) => t = i), c.setSelection(Zf(e, f, c.doc.resolve(t)));
	}
	e.focus(), e.dispatch(c.setMeta("uiEvent", "drop"));
}
Z.focus = (e) => {
	e.input.lastFocus = Date.now(), e.focused || (e.domObserver.stop(), e.dom.classList.add("ProseMirror-focused"), e.domObserver.start(), e.focused = !0, setTimeout(() => {
		e.docView && e.hasFocus() && !e.domObserver.currentSelection.eq(e.domSelectionRange()) && Hf(e);
	}, 20));
}, Z.blur = (e, t) => {
	let n = t;
	e.focused &&= (e.domObserver.stop(), e.dom.classList.remove("ProseMirror-focused"), e.domObserver.start(), n.relatedTarget && e.dom.contains(n.relatedTarget) && e.domObserver.currentSelection.clear(), !1);
}, Z.beforeinput = (e, t) => {
	if (kd && t.inputType == "deleteContentBackward") {
		e.domObserver.flushSoon();
		let { domChangeCount: t } = e.input;
		setTimeout(() => {
			if (e.input.domChangeCount != t || (e.dom.blur(), e.focus(), e.someProp("handleKeyDown", (t) => t(e, pd(8, "Backspace"))))) return;
			let { $cursor: n } = e.state.selection;
			n && n.pos > 0 && e.dispatch(e.state.tr.delete(n.pos - 1, n.pos).scrollIntoView());
		}, 50);
	}
};
for (let e in Q) Z[e] = Q[e];
function Cm(e, t) {
	if (e == t) return !0;
	for (let n in e) if (e[n] !== t[n]) return !1;
	for (let n in t) if (!(n in e)) return !1;
	return !0;
}
var wm = class {
	constructor(e) {
		this.frag = e, this.index = 0, this.off = 0;
	}
	nodeAt(e) {
		for (; this.off <= e && this.index < this.frag.childCount;) {
			let t = this.frag.child(this.index);
			if (this.off == e) return t;
			this.off += t.nodeSize, this.index++;
		}
		return null;
	}
}, Tm = class e {
	constructor(e, t) {
		this.toDOM = e, this.spec = t || Am, this.side = this.spec.side || 0;
	}
	map(e, t, n, r) {
		let { pos: i, deleted: a } = e.mapResult(t.from + r, this.side < 0 ? -1 : 1);
		return a ? null : new Om(i - n, i - n, this);
	}
	valid() {
		return !0;
	}
	eq(t) {
		return this == t || t instanceof e && (this.spec.key && this.spec.key == t.spec.key || this.toDOM == t.toDOM && Cm(this.spec, t.spec));
	}
	destroy(e) {
		this.spec.destroy && this.spec.destroy(e);
	}
}, Em = class e {
	constructor(e, t) {
		this.attrs = e, this.spec = t || Am;
	}
	map(e, t, n, r) {
		let i = e.map(t.from + r, this.spec.inclusiveStart ? -1 : 1) - n, a = e.map(t.to + r, this.spec.inclusiveEnd ? 1 : -1) - n;
		return i >= a ? null : new Om(i, a, this);
	}
	valid(e, t) {
		return t.from < t.to;
	}
	eq(t) {
		return this == t || t instanceof e && Cm(this.attrs, t.attrs) && Cm(this.spec, t.spec);
	}
	static is(t) {
		return t.type instanceof e;
	}
	destroy() {}
}, Dm = class e {
	constructor(e, t) {
		this.attrs = e, this.spec = t || Am;
	}
	map(e, t, n, r) {
		let i = e.mapResult(t.from + r, 1);
		if (i.deleted) return null;
		let a = e.mapResult(t.to + r, -1);
		return a.deleted || a.pos <= i.pos ? null : new Om(i.pos - n, a.pos - n, this);
	}
	valid(e, t, n) {
		let r = n.nodeAt(t.from);
		return !!r && !r.isText && t.from + r.nodeSize == t.to;
	}
	eq(t) {
		return this == t || t instanceof e && Cm(this.attrs, t.attrs) && Cm(this.spec, t.spec);
	}
	destroy() {}
}, Om = class e {
	constructor(e, t, n) {
		this.from = e, this.to = t, this.type = n;
	}
	copy(t, n) {
		return new e(t, n, this.type);
	}
	eq(e, t = 0) {
		return this.type.eq(e.type) && this.from + t == e.from && this.to + t == e.to;
	}
	map(e, t, n) {
		return this.type.map(e, this, t, n);
	}
	static widget(t, n, r) {
		return new e(t, t, new Tm(n, r));
	}
	static inline(t, n, r, i) {
		return new e(t, n, new Em(r, i));
	}
	static node(t, n, r, i) {
		return new e(t, n, new Dm(r, i));
	}
	get spec() {
		return this.type.spec;
	}
	get inline() {
		return this.type instanceof Em;
	}
	get widget() {
		return this.type instanceof Tm;
	}
}, km = [], Am = {}, jm = class e {
	constructor(e, t) {
		this.local = e.length ? e : km, this.children = t.length ? t : km;
	}
	static create(e, t) {
		return t.length ? Rm(t, e, 0, Am) : $;
	}
	find(e, t, n) {
		let r = [];
		return this.findInner(e ?? 0, t ?? 1e9, r, 0, n), r;
	}
	findInner(e, t, n, r, i) {
		for (let a = 0; a < this.local.length; a++) {
			let o = this.local[a];
			o.from <= t && o.to >= e && (!i || i(o.spec)) && n.push(o.copy(o.from + r, o.to + r));
		}
		for (let a = 0; a < this.children.length; a += 3) if (this.children[a] < t && this.children[a + 1] > e) {
			let o = this.children[a] + 1;
			this.children[a + 2].findInner(e - o, t - o, n, r + o, i);
		}
	}
	map(e, t, n) {
		return this == $ || e.maps.length == 0 ? this : this.mapInner(e, t, 0, 0, n || Am);
	}
	mapInner(t, n, r, i, a) {
		let o;
		if (this.local.length) {
			let e = new wm(n.content);
			for (let s = 0; s < this.local.length; s++) {
				let c = this.local[s].map(t, r, i);
				c && c.type.valid(n, c, e) ? (o ||= []).push(c) : a.onRemove && a.onRemove(this.local[s].spec);
			}
		}
		return this.children.length ? Nm(this.children, o || [], t, n, r, i, a) : o ? new e(o.sort(zm), km) : $;
	}
	add(t, n) {
		return n.length ? this == $ ? e.create(t, n) : this.addInner(t, n, 0) : this;
	}
	addInner(t, n, r) {
		let i, a = 0;
		t.forEach((e, t) => {
			let o = t + r, s;
			if (s = Im(n, e, o)) {
				for (i ||= this.children.slice(); a < i.length && i[a] < t;) a += 3;
				i[a] == t ? i[a + 2] = i[a + 2].addInner(e, s, o + 1) : i.splice(a, 0, t, t + e.nodeSize, Rm(s, e, o + 1, Am)), a += 3;
			}
		});
		let o = Pm(a ? Lm(n) : n, -r);
		if (o.length) {
			let e = new wm(t.content);
			for (let n = 0; n < o.length; n++) o[n].type.valid(t, o[n], e) || o.splice(n--, 1);
		}
		return new e(o.length ? this.local.concat(o).sort(zm) : this.local, i || this.children);
	}
	remove(e) {
		return e.length == 0 || this == $ ? this : this.removeInner(e, 0);
	}
	removeInner(t, n) {
		let r = this.children, i = this.local;
		for (let e = 0; e < r.length; e += 3) {
			let i, a = r[e] + n, o = r[e + 1] + n;
			for (let e = 0, n; e < t.length; e++) (n = t[e]) && n.from > a && n.to < o && (t[e] = null, (i ||= []).push(n));
			if (!i) continue;
			r == this.children && (r = this.children.slice());
			let s = r[e + 2].removeInner(i, a + 1);
			s == $ ? (r.splice(e, 3), e -= 3) : r[e + 2] = s;
		}
		if (i.length) {
			for (let e = 0, r; e < t.length; e++) if (r = t[e]) for (let e = 0; e < i.length; e++) i[e].eq(r, n) && (i == this.local && (i = this.local.slice()), i.splice(e--, 1));
		}
		return r == this.children && i == this.local ? this : i.length || r.length ? new e(i, r) : $;
	}
	forChild(t, n) {
		if (this == $) return this;
		if (n.isLeaf) return e.empty;
		let r, i;
		for (let e = 0; e < this.children.length; e += 3) if (this.children[e] >= t) {
			this.children[e] == t && (r = this.children[e + 2]);
			break;
		}
		let a = t + 1, o = a + n.content.size;
		for (let e = 0; e < this.local.length; e++) {
			let t = this.local[e];
			if (t.from < o && t.to > a && t.type instanceof Em) {
				let e = Math.max(a, t.from) - a, n = Math.min(o, t.to) - a;
				e < n && (i ||= []).push(t.copy(e, n));
			}
		}
		if (i) {
			let t = new e(i.sort(zm), km);
			return r ? new Mm([t, r]) : t;
		}
		return r || $;
	}
	eq(t) {
		if (this == t) return !0;
		if (!(t instanceof e) || this.local.length != t.local.length || this.children.length != t.children.length) return !1;
		for (let e = 0; e < this.local.length; e++) if (!this.local[e].eq(t.local[e])) return !1;
		for (let e = 0; e < this.children.length; e += 3) if (this.children[e] != t.children[e] || this.children[e + 1] != t.children[e + 1] || !this.children[e + 2].eq(t.children[e + 2])) return !1;
		return !0;
	}
	locals(e) {
		return Bm(this.localsInner(e));
	}
	localsInner(e) {
		if (this == $) return km;
		if (e.inlineContent || !this.local.some(Em.is)) return this.local;
		let t = [];
		for (let e = 0; e < this.local.length; e++) this.local[e].type instanceof Em || t.push(this.local[e]);
		return t;
	}
	forEachSet(e) {
		e(this);
	}
};
jm.empty = new jm([], []), jm.removeOverlap = Bm;
var $ = jm.empty, Mm = class e {
	constructor(e) {
		this.members = e;
	}
	map(t, n) {
		let r = this.members.map((e) => e.map(t, n, Am));
		return e.from(r);
	}
	forChild(t, n) {
		if (n.isLeaf) return jm.empty;
		let r = [];
		for (let i = 0; i < this.members.length; i++) {
			let a = this.members[i].forChild(t, n);
			a != $ && (a instanceof e ? r = r.concat(a.members) : r.push(a));
		}
		return e.from(r);
	}
	eq(t) {
		if (!(t instanceof e) || t.members.length != this.members.length) return !1;
		for (let e = 0; e < this.members.length; e++) if (!this.members[e].eq(t.members[e])) return !1;
		return !0;
	}
	locals(e) {
		let t, n = !0;
		for (let r = 0; r < this.members.length; r++) {
			let i = this.members[r].localsInner(e);
			if (i.length) {
				if (!t) t = i;
				else {
					n &&= (t = t.slice(), !1);
					for (let e = 0; e < i.length; e++) t.push(i[e]);
				}
			}
		}
		return t ? Bm(n ? t : t.sort(zm)) : km;
	}
	static from(t) {
		switch (t.length) {
			case 0: return $;
			case 1: return t[0];
			default: return new e(t.every((e) => e instanceof jm) ? t : t.reduce((e, t) => e.concat(t instanceof jm ? t : t.members), []));
		}
	}
	forEachSet(e) {
		for (let t = 0; t < this.members.length; t++) this.members[t].forEachSet(e);
	}
};
function Nm(e, t, n, r, i, a, o) {
	let s = e.slice();
	for (let e = 0, t = a; e < n.maps.length; e++) {
		let r = 0;
		n.maps[e].forEach((e, n, i, a) => {
			let o = a - i - (n - e);
			for (let i = 0; i < s.length; i += 3) {
				let a = s[i + 1];
				if (a < 0 || e > a + t - r) continue;
				let c = s[i] + t - r;
				n >= c ? s[i + 1] = e <= c ? -2 : -1 : e >= t && o && (s[i] += o, s[i + 1] += o);
			}
			r += o;
		}), t = n.maps[e].map(t, -1);
	}
	let c = !1;
	for (let t = 0; t < s.length; t += 3) if (s[t + 1] < 0) {
		if (s[t + 1] == -2) {
			c = !0, s[t + 1] = -1;
			continue;
		}
		let l = n.map(e[t] + a), u = l - i;
		if (u < 0 || u >= r.content.size) {
			c = !0;
			continue;
		}
		let d = n.map(e[t + 1] + a, -1) - i, { index: f, offset: p } = r.content.findIndex(u), m = r.maybeChild(f);
		if (m && p == u && p + m.nodeSize == d) {
			let r = s[t + 2].mapInner(n, m, l + 1, e[t] + a + 1, o);
			r == $ ? (s[t + 1] = -2, c = !0) : (s[t] = u, s[t + 1] = d, s[t + 2] = r);
		} else c = !0;
	}
	if (c) {
		let c = Rm(Fm(s, e, t, n, i, a, o), r, 0, o);
		t = c.local;
		for (let e = 0; e < s.length; e += 3) s[e + 1] < 0 && (s.splice(e, 3), e -= 3);
		for (let e = 0, t = 0; e < c.children.length; e += 3) {
			let n = c.children[e];
			for (; t < s.length && s[t] < n;) t += 3;
			s.splice(t, 0, c.children[e], c.children[e + 1], c.children[e + 2]);
		}
	}
	return new jm(t.sort(zm), s);
}
function Pm(e, t) {
	if (!t || !e.length) return e;
	let n = [];
	for (let r = 0; r < e.length; r++) {
		let i = e[r];
		n.push(new Om(i.from + t, i.to + t, i.type));
	}
	return n;
}
function Fm(e, t, n, r, i, a, o) {
	function s(e, t) {
		for (let a = 0; a < e.local.length; a++) {
			let s = e.local[a].map(r, i, t);
			s ? n.push(s) : o.onRemove && o.onRemove(e.local[a].spec);
		}
		for (let n = 0; n < e.children.length; n += 3) s(e.children[n + 2], e.children[n] + t + 1);
	}
	for (let n = 0; n < e.length; n += 3) e[n + 1] == -1 && s(e[n + 2], t[n] + a + 1);
	return n;
}
function Im(e, t, n) {
	if (t.isLeaf) return null;
	let r = n + t.nodeSize, i = null;
	for (let t = 0, a; t < e.length; t++) (a = e[t]) && a.from > n && a.to < r && ((i ||= []).push(a), e[t] = null);
	return i;
}
function Lm(e) {
	let t = [];
	for (let n = 0; n < e.length; n++) e[n] != null && t.push(e[n]);
	return t;
}
function Rm(e, t, n, r) {
	let i = [], a = !1;
	t.forEach((t, o) => {
		let s = Im(e, t, o + n);
		if (s) {
			a = !0;
			let e = Rm(s, t, n + o + 1, r);
			e != $ && i.push(o, o + t.nodeSize, e);
		}
	});
	let o = Pm(a ? Lm(e) : e, -n).sort(zm);
	if (o.length) {
		let e = new wm(t.content);
		for (let n = 0; n < o.length; n++) o[n].type.valid(t, o[n], e) || (r.onRemove && r.onRemove(o[n].spec), o.splice(n--, 1));
	}
	return o.length || i.length ? new jm(o, i) : $;
}
function zm(e, t) {
	return e.from - t.from || e.to - t.to;
}
function Bm(e) {
	let t = e;
	for (let n = 0; n < t.length - 1; n++) {
		let r = t[n];
		if (r.from != r.to) for (let i = n + 1; i < t.length; i++) {
			let a = t[i];
			if (a.from == r.from) a.to != r.to && (t == e && (t = e.slice()), t[i] = a.copy(a.from, r.to), Vm(t, i + 1, a.copy(r.to, a.to)));
			else {
				a.from < r.to && (t == e && (t = e.slice()), t[n] = r.copy(r.from, a.from), Vm(t, i, r.copy(a.from, r.to)));
				break;
			}
		}
	}
	return t;
}
function Vm(e, t, n) {
	for (; t < e.length && zm(n, e[t]) > 0;) t++;
	e.splice(t, 0, n);
}
function Hm(e) {
	let t = [];
	return e.someProp("decorations", (n) => {
		let r = n(e.state);
		r && r != $ && t.push(r);
	}), e.cursorWrapper && t.push(jm.create(e.state.doc, [e.cursorWrapper.deco])), Mm.from(t);
}
var Um = {
	childList: !0,
	characterData: !0,
	characterDataOldValue: !0,
	attributes: !0,
	attributeOldValue: !0,
	subtree: !0
}, Wm = J && Sd <= 11, Gm = class {
	constructor() {
		this.anchorNode = null, this.anchorOffset = 0, this.focusNode = null, this.focusOffset = 0;
	}
	set(e) {
		this.anchorNode = e.anchorNode, this.anchorOffset = e.anchorOffset, this.focusNode = e.focusNode, this.focusOffset = e.focusOffset;
	}
	clear() {
		this.anchorNode = this.focusNode = null;
	}
	eq(e) {
		return e.anchorNode == this.anchorNode && e.anchorOffset == this.anchorOffset && e.focusNode == this.focusNode && e.focusOffset == this.focusOffset;
	}
}, Km = class {
	constructor(e, t) {
		this.view = e, this.handleDOMChange = t, this.queue = [], this.flushingSoon = -1, this.observer = null, this.currentSelection = new Gm(), this.onCharData = null, this.suppressingSelectionUpdates = !1, this.lastChangedTextNode = null, this.observer = window.MutationObserver && new window.MutationObserver((t) => {
			for (let e = 0; e < t.length; e++) this.queue.push(t[e]);
			J && Sd <= 11 && t.some((e) => e.type == "childList" && e.removedNodes.length || e.type == "characterData" && e.oldValue.length > e.target.nodeValue.length) ? this.flushSoon() : X && e.composing && t.some((e) => e.type == "childList" && e.target.nodeName == "TR") ? (e.input.badSafariComposition = !0, this.flushSoon()) : this.flush();
		}), Wm && (this.onCharData = (e) => {
			this.queue.push({
				target: e.target,
				type: "characterData",
				oldValue: e.prevValue
			}), this.flushSoon();
		}), this.onSelectionChange = this.onSelectionChange.bind(this);
	}
	flushSoon() {
		this.flushingSoon < 0 && (this.flushingSoon = window.setTimeout(() => {
			this.flushingSoon = -1, this.flush();
		}, 20));
	}
	forceFlush() {
		this.flushingSoon > -1 && (window.clearTimeout(this.flushingSoon), this.flushingSoon = -1, this.flush());
	}
	start() {
		this.observer && (this.observer.takeRecords(), this.observer.observe(this.view.dom, Um)), this.onCharData && this.view.dom.addEventListener("DOMCharacterDataModified", this.onCharData), this.connectSelection();
	}
	stop() {
		if (this.observer) {
			let e = this.observer.takeRecords();
			if (e.length) {
				for (let t = 0; t < e.length; t++) this.queue.push(e[t]);
				window.setTimeout(() => this.flush(), 20);
			}
			this.observer.disconnect();
		}
		this.onCharData && this.view.dom.removeEventListener("DOMCharacterDataModified", this.onCharData), this.disconnectSelection();
	}
	connectSelection() {
		this.view.dom.ownerDocument.addEventListener("selectionchange", this.onSelectionChange);
	}
	disconnectSelection() {
		this.view.dom.ownerDocument.removeEventListener("selectionchange", this.onSelectionChange);
	}
	suppressSelectionUpdates() {
		this.suppressingSelectionUpdates = !0, setTimeout(() => this.suppressingSelectionUpdates = !1, 50);
	}
	onSelectionChange() {
		if (Qf(this.view)) {
			if (this.suppressingSelectionUpdates) return Hf(this.view);
			if (J && Sd <= 11 && !this.view.state.selection.empty) {
				let e = this.view.domSelectionRange();
				if (e.focusNode && id(e.focusNode, e.focusOffset, e.anchorNode, e.anchorOffset)) return this.flushSoon();
			}
			this.flush();
		}
	}
	setCurSelection() {
		this.currentSelection.set(this.view.domSelectionRange());
	}
	ignoreSelectionChange(e) {
		if (!e.focusNode) return !0;
		let t = /* @__PURE__ */ new Set(), n;
		for (let n = e.focusNode; n; n = ed(n)) t.add(n);
		for (let r = e.anchorNode; r; r = ed(r)) if (t.has(r)) {
			n = r;
			break;
		}
		let r = n && this.view.docView.nearestDesc(n);
		if (r && r.ignoreMutation({
			type: "selection",
			target: q(n) ? n.parentNode : n
		})) return this.setCurSelection(), !0;
	}
	pendingRecords() {
		if (this.observer) for (let e of this.observer.takeRecords()) this.queue.push(e);
		return this.queue;
	}
	flush() {
		let { view: e } = this;
		if (!e.docView || this.flushingSoon > -1) return;
		let t = this.pendingRecords();
		t.length && (this.queue = []);
		let n, r = !1;
		!this.suppressingSelectionUpdates && Qf(e) && (n = e.domSelectionRange(), r = !this.currentSelection.eq(n) && !this.ignoreSelectionChange(n));
		let i = -1, a = -1, o = !1, s = [];
		if (e.editable) for (let e = 0; e < t.length; e++) {
			let n = this.registerMutation(t[e], s);
			n && (i = i < 0 ? n.from : Math.min(n.from, i), a = a < 0 ? n.to : Math.max(n.to, a), n.typeOver && (o = !0));
		}
		if (s.some((e) => e.nodeName == "BR") && (e.input.lastKeyCode == 8 || e.input.lastKeyCode == 46 || Y && (e.composing || e.input.compositionEndedAt > Date.now() - 50) && t.some((e) => e.type == "childList" && e.removedNodes.length))) {
			for (let e of s) if (e.nodeName == "BR" && e.parentNode) {
				let t = e.nextSibling;
				for (; t && K(t);) {
					if (t.contentEditable == "false") {
						e.parentNode.removeChild(e);
						break;
					}
					t = t.firstChild;
				}
			}
		} else if (Cd && s.length) {
			let t = s.filter((e) => e.nodeName == "BR");
			if (t.length == 2) {
				let [e, n] = t;
				e.parentNode && e.parentNode.parentNode == n.parentNode ? n.remove() : e.remove();
			} else {
				let { focusNode: n } = this.currentSelection;
				for (let r of t) {
					let t = r.parentNode;
					t && t.nodeName == "LI" && (!n || Qm(e, n) != t) && r.remove();
				}
			}
		}
		let c = null;
		i < 0 && r && e.input.lastFocus > Date.now() - 200 && Math.max(e.input.lastTouch, e.input.lastClick.time) < Date.now() - 300 && fd(n) && (c = Bf(e)) && c.eq(V.near(e.state.doc.resolve(0), 1)) ? (e.input.lastFocus = 0, Hf(e), this.currentSelection.set(n), e.scrollToSelection()) : (i > -1 || r) && (i > -1 && (e.docView.markDirty(i, a), Ym(e)), e.input.badSafariComposition && (e.input.badSafariComposition = !1, $m(e, s)), this.handleDOMChange(i, a, o, s), e.docView && e.docView.dirty ? e.updateState(e.state) : n && !this.currentSelection.eq(n) && Hf(e), n && this.currentSelection.set(n));
	}
	registerMutation(e, t) {
		if (t.indexOf(e.target) > -1) return null;
		let n = this.view.docView.nearestDesc(e.target);
		if (e.type == "attributes" && (n == this.view.docView || e.attributeName == "contenteditable" || e.attributeName == "style" && !e.oldValue && !e.target.getAttribute("style")) || !n || n.ignoreMutation(e)) return null;
		if (e.type == "childList") {
			for (let n = 0; n < e.addedNodes.length; n++) {
				let r = e.addedNodes[n];
				t.push(r), q(r) && (this.lastChangedTextNode = r);
			}
			if (n.contentDOM && n.contentDOM != n.dom && !n.contentDOM.contains(e.target)) return {
				from: n.posBefore,
				to: n.posAfter
			};
			let r = e.previousSibling, i = e.nextSibling;
			if (J && Sd <= 11 && e.addedNodes.length) for (let t = 0; t < e.addedNodes.length; t++) {
				let { previousSibling: n, nextSibling: a } = e.addedNodes[t];
				(!n || Array.prototype.indexOf.call(e.addedNodes, n) < 0) && (r = n), (!a || Array.prototype.indexOf.call(e.addedNodes, a) < 0) && (i = a);
			}
			let a = r && r.parentNode == e.target ? W(r) + 1 : 0, o = n.localPosFromDOM(e.target, a, -1), s = i && i.parentNode == e.target ? W(i) : e.target.childNodes.length;
			return {
				from: o,
				to: n.localPosFromDOM(e.target, s, 1)
			};
		}
		return e.type == "attributes" ? {
			from: n.posAtStart - n.border,
			to: n.posAtEnd + n.border
		} : (this.lastChangedTextNode = e.target, {
			from: n.posAtStart,
			to: n.posAtEnd,
			typeOver: e.target.nodeValue == e.oldValue
		});
	}
}, qm = /* @__PURE__ */ new WeakMap(), Jm = !1;
function Ym(e) {
	if (!qm.has(e) && (qm.set(e, null), [
		"normal",
		"nowrap",
		"pre-line"
	].indexOf(getComputedStyle(e.dom).whiteSpace) !== -1)) {
		if (e.requiresGeckoHackNode = Cd, Jm) return;
		console.warn("ProseMirror expects the CSS white-space property to be set, preferably to 'pre-wrap'. It is recommended to load style/prosemirror.css from the prosemirror-view package."), Jm = !0;
	}
}
function Xm(e, t) {
	let n = t.startContainer, r = t.startOffset, i = t.endContainer, a = t.endOffset, o = e.domAtPos(e.state.selection.anchor);
	return id(o.node, o.offset, i, a) && ([n, r, i, a] = [
		i,
		a,
		n,
		r
	]), {
		anchorNode: n,
		anchorOffset: r,
		focusNode: i,
		focusOffset: a
	};
}
function Zm(e, t) {
	if (t.getComposedRanges) {
		let n = t.getComposedRanges(e.root)[0];
		if (n) return Xm(e, n);
	}
	let n;
	function r(e) {
		e.preventDefault(), e.stopImmediatePropagation(), n = e.getTargetRanges()[0];
	}
	return e.dom.addEventListener("beforeinput", r, !0), document.execCommand("indent"), e.dom.removeEventListener("beforeinput", r, !0), n ? Xm(e, n) : null;
}
function Qm(e, t) {
	for (let n = t.parentNode; n && n != e.dom; n = n.parentNode) {
		let t = e.docView.nearestDesc(n, !0);
		if (t && t.node.isBlock) return n;
	}
	return null;
}
function $m(e, t) {
	let { focusNode: n, focusOffset: r } = e.domSelectionRange();
	for (let i of t) if (i.parentNode?.nodeName == "TR") {
		let t = i.nextSibling;
		for (; t && t.nodeName != "TD" && t.nodeName != "TH";) t = t.nextSibling;
		if (t) {
			let a = t;
			for (;;) {
				let e = a.firstChild;
				if (!e || !K(e) || e.contentEditable == "false" || /^(BR|IMG)$/.test(e.nodeName)) break;
				a = e;
			}
			a.insertBefore(i, a.firstChild), n == i && e.domSelection().collapse(i, r);
		} else i.parentNode.removeChild(i);
	}
}
function eh(e, t, n, r) {
	let { node: i, fromOffset: a, toOffset: o, from: s, to: c } = e.docView.parseRange(t, n), l = e.domSelectionRange(), u, d = l.anchorNode;
	if (d && sd(e.dom, d) && (u = [{
		node: d,
		offset: l.anchorOffset
	}], fd(l) || u.push({
		node: l.focusNode,
		offset: l.focusOffset
	})), Y && e.input.lastKeyCode === 8) for (let e = o; e > a; e--) {
		let t = i.childNodes[e - 1], n = t.pmViewDesc;
		if (t.nodeName == "BR" && !n) {
			o = e;
			break;
		}
		if (!n || n.size) break;
	}
	let f = e.state.doc, p = e.someProp("domParser") || $o.fromSchema(e.state.schema), m = f.resolve(s), h = null, g = p.parse(i, {
		topNode: m.parent,
		topMatch: m.parent.contentMatchAt(m.index()),
		topOpen: !0,
		from: a,
		to: o,
		preserveWhitespace: m.parent.type.whitespace != "pre" || "full",
		findPositions: u,
		ruleFromNode: th(r),
		context: m
	});
	if (u && u[0].pos != null) {
		let e = u[0].pos, t = u[1] && u[1].pos;
		t ??= e, h = {
			anchor: e + s,
			head: t + s
		};
	}
	return {
		doc: g,
		sel: h,
		from: s,
		to: c
	};
}
var th = (e) => (t) => {
	let n = t.pmViewDesc;
	if (n) return n.parseRule(e);
	if (t.nodeName == "BR" && t.parentNode) {
		if (X && /^(ul|ol)$/i.test(t.parentNode.nodeName)) {
			let e = document.createElement("div");
			return e.appendChild(document.createElement("li")), { skip: e };
		}
		if (t.parentNode.lastChild == t || X && /^(tr|table)$/i.test(t.parentNode.nodeName)) return { ignore: !0 };
	} else if (t.nodeName == "IMG" && t.getAttribute("mark-placeholder")) return { ignore: !0 };
	return null;
}, nh = /^(a|abbr|acronym|b|bd[io]|big|br|button|cite|code|data(list)?|del|dfn|em|i|img|ins|kbd|label|map|mark|meter|output|q|ruby|s|samp|small|span|strong|su[bp]|time|u|tt|var)$/i;
function rh(e, t, n, r, i) {
	let a = e.input.compositionPendingChanges || (e.composing ? e.input.compositionID : 0);
	if (e.input.compositionPendingChanges = 0, t < 0) {
		let t = e.input.lastSelectionTime > Date.now() - 50 ? e.input.lastSelectionOrigin : null, n = Bf(e, t);
		if (n && !e.state.selection.eq(n)) {
			if (Y && kd && e.input.lastKeyCode === 13 && Date.now() - 100 < e.input.lastKeyCodeTime && e.someProp("handleKeyDown", (t) => t(e, pd(13, "Enter")))) return;
			let r = e.state.tr.setSelection(n);
			t == "pointer" ? r.setMeta("pointer", !0) : t == "key" && r.scrollIntoView(), a && r.setMeta("composition", a), e.dispatch(r);
		}
		return;
	}
	let o = e.state.doc.resolve(t), s = o.sharedDepth(n);
	t = o.before(s + 1), n = e.state.doc.resolve(n).after(s + 1);
	let c = e.state.selection, l = eh(e, t, n, i), u = e.state.doc, d = u.slice(l.from, l.to), f, p;
	e.input.lastKeyCode === 8 && Date.now() - 100 < e.input.lastKeyCodeTime ? (f = e.state.selection.to, p = "end") : (f = e.state.selection.from, p = "start"), e.input.lastKeyCode = null;
	let m = lh(d.content, l.doc.content, l.from, f, p);
	if (m && e.input.domChangeCount++, (Ed && e.input.lastIOSEnter > Date.now() - 225 || kd) && i.some((e) => K(e) && !nh.test(e.nodeName)) && (!m || m.endA >= m.endB) && e.someProp("handleKeyDown", (t) => t(e, pd(13, "Enter")))) {
		e.input.lastIOSEnter = 0;
		return;
	}
	if (!m) {
		if (r && c instanceof H && !c.empty && c.$head.sameParent(c.$anchor) && !e.composing && !(l.sel && l.sel.anchor != l.sel.head)) m = {
			start: c.from,
			endA: c.to,
			endB: c.to
		};
		else {
			if (l.sel) {
				let t = ih(e, e.state.doc, l.sel);
				if (t && !t.eq(e.state.selection)) {
					let n = e.state.tr.setSelection(t);
					a && n.setMeta("composition", a), e.dispatch(n);
				}
			}
			return;
		}
	}
	e.state.selection.from < e.state.selection.to && m.start == m.endB && e.state.selection instanceof H && (m.start > e.state.selection.from && m.start <= e.state.selection.from + 2 && e.state.selection.from >= l.from ? m.start = e.state.selection.from : m.endA < e.state.selection.to && m.endA >= e.state.selection.to - 2 && e.state.selection.to <= l.to && (m.endB += e.state.selection.to - m.endA, m.endA = e.state.selection.to)), J && Sd <= 11 && m.endB == m.start + 1 && m.endA == m.start && m.start > l.from && l.doc.textBetween(m.start - l.from - 1, m.start - l.from + 1) == " \xA0" && (m.start--, m.endA--, m.endB--);
	let h = l.doc.resolveNoCache(m.start - l.from), g = l.doc.resolveNoCache(m.endB - l.from), _ = u.resolve(m.start), v = h.sameParent(g) && h.parent.inlineContent && _.end() >= m.endA;
	if ((Ed && e.input.lastIOSEnter > Date.now() - 225 && (!v || i.some((e) => e.nodeName == "DIV" || e.nodeName == "P")) || !v && sh(l.doc, h.pos, g.pos)) && e.someProp("handleKeyDown", (t) => t(e, pd(13, "Enter")))) {
		e.input.lastIOSEnter = 0;
		return;
	}
	if (e.state.selection.anchor > m.start && (v ? m.endA > m.start && m.endB == m.start : oh(u, m.start, m.endA, h, g)) && e.someProp("handleKeyDown", (t) => t(e, pd(8, "Backspace")))) {
		kd && Y && e.domObserver.suppressSelectionUpdates();
		return;
	}
	Y && m.endB == m.start && (e.input.lastChromeDelete = Date.now()), kd && !v && h.start() != g.start() && g.parentOffset == 0 && h.depth == g.depth && l.sel && l.sel.anchor == l.sel.head && l.sel.head == m.endA && (m.endB -= 2, g = l.doc.resolveNoCache(m.endB - l.from), setTimeout(() => {
		e.someProp("handleKeyDown", function(t) {
			return t(e, pd(13, "Enter"));
		});
	}, 20));
	let y = m.start, b = m.endA, x = (t) => {
		let n = t || e.state.tr.replace(y, b, l.doc.slice(m.start - l.from, m.endB - l.from));
		if (l.sel) {
			let t = ih(e, n.doc, l.sel);
			t && !(Y && e.composing && t.empty && (m.start != m.endB || e.input.lastChromeDelete < Date.now() - 100) && (t.head == y || t.head == n.mapping.map(b) - 1) || J && t.empty && t.head == y) && n.setSelection(t);
		}
		return a && n.setMeta("composition", a), n.scrollIntoView();
	}, ee;
	if (v) {
		if (h.pos == g.pos) {
			J && Sd <= 11 && h.parentOffset == 0 && (e.domObserver.suppressSelectionUpdates(), setTimeout(() => Hf(e), 20));
			let t = x(e.state.tr.delete(y, b)), n = u.resolve(m.start).marksAcross(u.resolve(m.endA));
			n && t.ensureMarks(n), e.dispatch(t);
		} else if (m.endA == m.endB && (ee = ah(h.parent.content.cut(h.parentOffset, g.parentOffset), _.parent.content.cut(_.parentOffset, m.endA - _.start())))) {
			let t = x(e.state.tr);
			ee.type == "add" ? t.addMark(y, b, ee.mark) : t.removeMark(y, b, ee.mark), e.dispatch(t);
		} else if (h.parent.child(h.index()).isText && h.index() == g.index() - +!g.textOffset) {
			let t = h.parent.textBetween(h.parentOffset, g.parentOffset), n = () => x(e.state.tr.insertText(t, y, b));
			e.someProp("handleTextInput", (r) => r(e, y, b, t, n)) || e.dispatch(n());
		} else e.dispatch(x());
	} else e.dispatch(x());
}
function ih(e, t, n) {
	return Math.max(n.anchor, n.head) > t.content.size ? null : Zf(e, t.resolve(n.anchor), t.resolve(n.head));
}
function ah(e, t) {
	let n = e.firstChild.marks, r = t.firstChild.marks, i = n, a = r, o, s, c;
	for (let e = 0; e < r.length; e++) i = r[e].removeFromSet(i);
	for (let e = 0; e < n.length; e++) a = n[e].removeFromSet(a);
	if (i.length == 1 && a.length == 0) s = i[0], o = "add", c = (e) => e.mark(s.addToSet(e.marks));
	else if (i.length == 0 && a.length == 1) s = a[0], o = "remove", c = (e) => e.mark(s.removeFromSet(e.marks));
	else return null;
	let l = [];
	for (let e = 0; e < t.childCount; e++) l.push(c(t.child(e)));
	if (I.from(l).eq(e)) return {
		mark: s,
		type: o
	};
}
function oh(e, t, n, r, i) {
	if (n - t <= i.pos - r.pos || ch(r, !0, !1) < i.pos) return !1;
	let a = e.resolve(t);
	if (!r.parent.inlineContent) {
		let e = a.nodeAfter;
		return e != null && n == t + e.nodeSize;
	}
	if (a.parentOffset < a.parent.content.size || !a.parent.isTextblock) return !1;
	let o = e.resolve(ch(a, !0, !0));
	return !o.parent.isTextblock || o.pos > n || ch(o, !0, !1) < n ? !1 : r.parent.content.cut(r.parentOffset).eq(o.parent.content);
}
function sh(e, t, n) {
	let r = 0, i = 0;
	return e.nodesBetween(t, n, (e, a) => {
		e.isTextblock && a >= t && a < n ? r++ : (e.isText ? /\S/.test(e.text.slice(Math.max(0, t - a), Math.min(e.nodeSize, n - a))) : e.isLeaf) && i++;
	}), i == 0 && r == 1;
}
function ch(e, t, n) {
	let r = e.depth, i = t ? e.end() : e.pos;
	for (; r > 0 && (t || e.indexAfter(r) == e.node(r).childCount);) r--, i++, t = !1;
	if (n) {
		let t = e.node(r).maybeChild(e.indexAfter(r));
		for (; t && !t.isLeaf;) t = t.firstChild, i++;
	}
	return i;
}
function lh(e, t, n, r, i) {
	let a = e.findDiffStart(t, n), o = n + e.size, s = n + t.size;
	if (a == null) return null;
	let { a: c, b: l } = e.findDiffEnd(t, o, s);
	if (i == "end") {
		let e = Math.max(0, a - Math.min(c, l));
		r -= c + e - a;
	}
	if (c < a && o < s) {
		let e = r <= a && r >= c ? a - r : 0;
		a -= e, l = a + (l - c), c = a;
	} else if (l < a) {
		let e = r <= a && r >= l ? a - r : 0;
		a -= e, c = a + (c - l), l = a;
	}
	return {
		start: a,
		endA: c,
		endB: l
	};
}
var uh = class {
	constructor(e, t) {
		this._root = null, this.focused = !1, this.trackWrites = null, this.mounted = !1, this.markCursor = null, this.cursorWrapper = null, this.lastSelectedViewDesc = void 0, this.input = new Ip(), this.prevDirectPlugins = [], this.pluginViews = [], this.requiresGeckoHackNode = !1, this.dragging = null, this._props = t, this.state = t.state, this.directPlugins = t.plugins || [], this.directPlugins.forEach(_h), this.dispatch = this.dispatch.bind(this), this.dom = e && e.mount || document.createElement("div"), e && (e.appendChild ? e.appendChild(this.dom) : typeof e == "function" ? e(this.dom) : e.mount && (this.mounted = !0)), this.editable = ph(this), fh(this), this.nodeViews = hh(this), this.docView = bf(this.state.doc, dh(this), Hm(this), this.dom, this), this.domObserver = new Km(this, (e, t, n, r) => rh(this, e, t, n, r)), this.domObserver.start(), Lp(this), this.updatePluginViews();
	}
	get composing() {
		return this.input.composing;
	}
	get props() {
		if (this._props.state != this.state) {
			let e = this._props;
			this._props = {};
			for (let t in e) this._props[t] = e[t];
			this._props.state = this.state;
		}
		return this._props;
	}
	update(e) {
		e.handleDOMEvents != this._props.handleDOMEvents && Bp(this);
		let t = this._props;
		this._props = e, e.plugins && (e.plugins.forEach(_h), this.directPlugins = e.plugins), this.updateStateInner(e.state, t);
	}
	setProps(e) {
		let t = {};
		for (let e in this._props) t[e] = this._props[e];
		t.state = this.state;
		for (let n in e) t[n] = e[n];
		this.update(t);
	}
	updateState(e) {
		this.updateStateInner(e, this._props);
	}
	updateStateInner(e, t) {
		let n = this.state, r = !1, i = !1;
		e.storedMarks && this.composing && (um(this), i = !0), this.state = e;
		let a = n.plugins != e.plugins || this._props.plugins != t.plugins;
		if (a || this._props.plugins != t.plugins || this._props.nodeViews != t.nodeViews) {
			let e = hh(this);
			gh(e, this.nodeViews) && (this.nodeViews = e, r = !0);
		}
		(a || t.handleDOMEvents != this._props.handleDOMEvents) && Bp(this), this.editable = ph(this), fh(this);
		let o = Hm(this), s = dh(this), c = n.plugins != e.plugins && !n.doc.eq(e.doc) ? "reset" : e.scrollToSelection > n.scrollToSelection ? "to selection" : "preserve", l = r || !this.docView.matchesNode(e.doc, s, o);
		(l || !e.selection.eq(n.selection)) && (i = !0);
		let u = c == "preserve" && i && this.dom.style.overflowAnchor == null && Id(this);
		if (i) {
			this.domObserver.stop();
			let t = l && (J || Y) && !this.composing && !n.selection.empty && !e.selection.empty && mh(n.selection, e.selection);
			if (l) {
				let n = Y ? this.trackWrites = this.domSelectionRange().focusNode : null;
				this.composing && (this.input.compositionNode = dm(this)), (r || !this.docView.update(e.doc, s, o, this)) && (this.docView.updateOuterDeco(s), this.docView.destroy(), this.docView = bf(e.doc, s, o, this.dom, this)), !n || this.trackWrites && this.dom.contains(this.trackWrites) || (t = !0);
			}
			let i = this.input.mouseDown;
			t || !(i && this.domObserver.currentSelection.eq(this.domSelectionRange()) && ep(this) && i.delaySelUpdate()) ? Hf(this, t) : (Yf(this, e.selection), this.domObserver.setCurSelection()), this.domObserver.start();
		}
		this.updatePluginViews(n), this.dragging?.node && !n.doc.eq(e.doc) && this.updateDraggedNode(this.dragging, n), c == "reset" ? this.dom.scrollTop = 0 : c == "to selection" ? this.scrollToSelection() : u && Rd(u);
	}
	scrollToSelection() {
		let e = this.domSelectionRange().focusNode;
		if (e && sd(this.dom, e) && !this.someProp("handleScrollToSelection", (e) => e(this))) {
			if (this.state.selection instanceof U) {
				let t = this.docView.domAfterPos(this.state.selection.from);
				K(t) && Fd(this, t.getBoundingClientRect(), e);
			} else Fd(this, this.coordsAtPos(this.state.selection.head, 1), e);
		}
	}
	destroyPluginViews() {
		let e;
		for (; e = this.pluginViews.pop();) e.destroy && e.destroy();
	}
	updatePluginViews(e) {
		if (!e || e.plugins != this.state.plugins || this.directPlugins != this.prevDirectPlugins) {
			this.prevDirectPlugins = this.directPlugins, this.destroyPluginViews();
			for (let e = 0; e < this.directPlugins.length; e++) {
				let t = this.directPlugins[e];
				t.spec.view && this.pluginViews.push(t.spec.view(this));
			}
			for (let e = 0; e < this.state.plugins.length; e++) {
				let t = this.state.plugins[e];
				t.spec.view && this.pluginViews.push(t.spec.view(this));
			}
		} else for (let t = 0; t < this.pluginViews.length; t++) {
			let n = this.pluginViews[t];
			n.update && n.update(this, e);
		}
	}
	updateDraggedNode(e, t) {
		let n = e.node, r = -1;
		if (n.from < this.state.doc.content.size && this.state.doc.nodeAt(n.from) == n.node) r = n.from;
		else {
			let e = n.from + (this.state.doc.content.size - t.doc.content.size);
			(e > 0 && e < this.state.doc.content.size && this.state.doc.nodeAt(e)) == n.node && (r = e);
		}
		this.dragging = new ym(e.slice, e.move, r < 0 ? void 0 : U.create(this.state.doc, r));
	}
	someProp(e, t) {
		let n = this._props && this._props[e], r;
		if (n != null && (r = t ? t(n) : n)) return r;
		for (let n = 0; n < this.directPlugins.length; n++) {
			let i = this.directPlugins[n].props[e];
			if (i != null && (r = t ? t(i) : i)) return r;
		}
		let i = this.state.plugins;
		if (i) for (let n = 0; n < i.length; n++) {
			let a = i[n].props[e];
			if (a != null && (r = t ? t(a) : a)) return r;
		}
	}
	hasFocus() {
		if (J) {
			let e = this.root.activeElement;
			if (e == this.dom) return !0;
			if (!e || !this.dom.contains(e)) return !1;
			for (; e && this.dom != e && this.dom.contains(e);) {
				if (e.contentEditable == "false") return !1;
				e = e.parentElement;
			}
			return !0;
		}
		return this.root.activeElement == this.dom;
	}
	focus() {
		this.domObserver.stop(), this.editable && Vd(this.dom), Hf(this), this.domObserver.start();
	}
	get root() {
		let e = this._root;
		if (e == null) {
			for (let e = this.dom.parentNode; e; e = e.parentNode) if (e.nodeType == 9 || e.nodeType == 11 && e.host) return e.getSelection || (Object.getPrototypeOf(e).getSelection = () => e.ownerDocument.getSelection()), this._root = e;
		}
		return e || document;
	}
	updateRoot() {
		this._root = null;
	}
	posAtCoords(e) {
		return Yd(this, e);
	}
	coordsAtPos(e, t = 1) {
		return $d(this, e, t);
	}
	domAtPos(e, t = 0) {
		return this.docView.domFromPos(e, t);
	}
	nodeDOM(e) {
		let t = this.docView.descAt(e);
		return t ? t.nodeDOM : null;
	}
	posAtDOM(e, t, n = -1) {
		let r = this.docView.posFromDOM(e, t, n);
		if (r == null) throw RangeError("DOM position not inside the editor");
		return r;
	}
	endOfTextblock(e, t) {
		return uf(this, t || this.state, e);
	}
	pasteHTML(e, t) {
		return _m(this, "", e, !1, t || new ClipboardEvent("paste"));
	}
	pasteText(e, t) {
		return _m(this, e, null, !0, t || new ClipboardEvent("paste"));
	}
	serializeForClipboard(e) {
		return yp(this, e);
	}
	destroy() {
		this.docView && (zp(this), this.destroyPluginViews(), this.mounted ? (this.docView.update(this.state.doc, [], Hm(this), this), this.dom.textContent = "") : this.dom.parentNode && this.dom.parentNode.removeChild(this.dom), this.docView.destroy(), this.docView = null, rd());
	}
	get isDestroyed() {
		return this.docView == null;
	}
	dispatchEvent(e) {
		return Up(this, e);
	}
	domSelectionRange() {
		let e = this.dom.isConnected !== !1 && this.domSelection();
		return e ? X && this.root.nodeType === 11 && md(this.dom.ownerDocument) == this.dom && Zm(this, e) || e : {
			focusNode: null,
			focusOffset: 0,
			anchorNode: null,
			anchorOffset: 0
		};
	}
	domSelection() {
		return this.root.getSelection();
	}
};
uh.prototype.dispatch = function(e) {
	let t = this._props.dispatchTransaction;
	t ? t.call(this, e) : this.updateState(this.state.apply(e));
};
function dh(e) {
	let t = Object.create(null);
	return t.class = "ProseMirror", t.contenteditable = String(e.editable), e.someProp("attributes", (n) => {
		if (typeof n == "function" && (n = n(e.state)), n) for (let e in n) e == "class" ? t.class += " " + n[e] : e == "style" ? t.style = (t.style ? t.style + ";" : "") + n[e] : !t[e] && e != "contenteditable" && e != "nodeName" && (t[e] = String(n[e]));
	}), t.translate ||= "no", [Om.node(0, e.state.doc.content.size, t)];
}
function fh(e) {
	if (e.markCursor) {
		let t = document.createElement("img");
		t.className = "ProseMirror-separator", t.setAttribute("mark-placeholder", "true"), t.setAttribute("alt", ""), e.cursorWrapper = {
			dom: t,
			deco: Om.widget(e.state.selection.from, t, {
				raw: !0,
				marks: e.markCursor
			})
		};
	} else e.cursorWrapper = null;
}
function ph(e) {
	return !e.someProp("editable", (t) => t(e.state) === !1);
}
function mh(e, t) {
	let n = Math.min(e.$anchor.sharedDepth(e.head), t.$anchor.sharedDepth(t.head));
	return e.$anchor.start(n) != t.$anchor.start(n);
}
function hh(e) {
	let t = Object.create(null);
	function n(e) {
		for (let n in e) Object.prototype.hasOwnProperty.call(t, n) || (t[n] = e[n]);
	}
	return e.someProp("nodeViews", n), e.someProp("markViews", n), t;
}
function gh(e, t) {
	let n = 0, r = 0;
	for (let r in e) {
		if (e[r] != t[r]) return !0;
		n++;
	}
	for (let e in t) r++;
	return n != r;
}
function _h(e) {
	if (e.spec.state || e.spec.filterTransaction || e.spec.appendTransaction) throw RangeError("Plugins passed directly to the view must not have a state component");
}
// node_modules/.pnpm/@milkdown+core@7.23.0/node_modules/@milkdown/core/lib/index.js
function vh(e, t) {
	return e.meta = {
		package: "@milkdown/core",
		group: "System",
		...t
	}, e;
}
var yh = {
	text: (e, t, n, r) => {
		let i = e.value;
		return /^[^*_\\]*\s+$/.test(i) ? i : n.safe(i, {
			...r,
			encode: []
		});
	},
	strong: (e, t, n, r) => {
		let i = e.marker || n.options.strong || "*", a = n.enter("strong"), o = n.createTracker(r), s = o.move(i + i);
		return s += o.move(n.containerPhrasing(e, {
			before: s,
			after: i,
			...o.current()
		})), s += o.move(i + i), a(), s;
	},
	emphasis: (e, t, n, r) => {
		let i = e.marker || n.options.emphasis || "*", a = n.enter("emphasis"), o = n.createTracker(r), s = o.move(i);
		return s += o.move(n.containerPhrasing(e, {
			before: s,
			after: i,
			...o.current()
		})), s += o.move(i), a(), s;
	}
}, bh = T({}, "editorView"), xh = T({}, "editorState"), Sh = T([], "initTimer"), Ch = T({}, "editor"), wh = T([], "inputRules"), Th = T([], "prosePlugins"), Eh = T([], "remarkPlugins"), Dh = T([], "nodeView"), Oh = T([], "markView"), kh = T(Va().use(lr).use(pa), "remark"), Ah = T({
	handlers: yh,
	encode: []
}, "remarkStringifyOptions"), jh = E("ConfigReady");
function Mh(e) {
	let t = (t) => (t.record(jh), async () => (await e(t), t.done(jh), () => {
		t.clearTimer(jh);
	}));
	return vh(t, { displayName: "Config" }), t;
}
var Nh = E("InitReady");
function Ph(e) {
	let t = (t) => (t.inject(Ch, e).inject(Th, []).inject(Eh, []).inject(wh, []).inject(Dh, []).inject(Oh, []).inject(Ah, {
		handlers: yh,
		encode: []
	}).inject(kh, Va().use(lr).use(pa)).inject(Sh, [jh]).record(Nh), async () => {
		await t.waitTimers(Sh);
		let e = t.get(Ah);
		return t.set(kh, Va().use(lr).use(pa, e)), t.done(Nh), () => {
			t.remove(Ch).remove(Th).remove(Eh).remove(wh).remove(Dh).remove(Oh).remove(Ah).remove(kh).remove(Sh).clearTimer(Nh);
		};
	});
	return vh(t, { displayName: "Init" }), t;
}
var Fh = E("SchemaReady"), Ih = T([], "schemaTimer"), Lh = T({}, "schema"), Rh = T([], "nodes"), zh = T([], "marks");
function Bh(e) {
	return {
		...e,
		parseDOM: e.parseDOM?.map((t) => ({
			priority: e.priority,
			...t
		}))
	};
}
var Vh = (e) => (e.inject(Lh, {}).inject(Rh, []).inject(zh, []).inject(Ih, [Nh]).record(Fh), async () => {
	await e.waitTimers(Ih);
	let t = e.get(kh), n = e.get(Eh).reduce((e, t) => e.use(t.plugin, t.options), t);
	e.set(kh, n);
	let r = new Yo({
		nodes: Object.fromEntries(e.get(Rh).map(([e, t]) => [e, Bh(t)])),
		marks: Object.fromEntries(e.get(zh).map(([e, t]) => [e, Bh(t)]))
	});
	return e.set(Lh, r), e.done(Fh), () => {
		e.remove(Lh).remove(Rh).remove(zh).remove(Ih).clearTimer(Fh);
	};
});
vh(Vh, { displayName: "Schema" });
var Hh = class {
	constructor() {
		this.#e = new C(), this.#t = null, this.setCtx = (e) => {
			this.#t = e;
		}, this.chain = () => {
			if (this.#t == null) throw y();
			let e = this.#t, t = [], n = this.get.bind(this), r = {
				run: () => {
					let n = Fl(...t), r = e.get(bh);
					return n(r.state, r.dispatch, r);
				},
				inline: (e) => (t.push(e), r),
				pipe: i.bind(this)
			};
			function i(e, i) {
				let a = n(e);
				return t.push(a(i)), r;
			}
			return r;
		};
	}
	#e;
	#t;
	get ctx() {
		return this.#t;
	}
	create(e, t) {
		let n = e.create(this.#e.sliceMap);
		return n.set(t), n;
	}
	get(e) {
		return this.#e.get(e).get();
	}
	remove(e) {
		return this.#e.remove(e);
	}
	call(e, t) {
		if (this.#t == null) throw y();
		let n = this.get(e)(t), r = this.#t.get(bh);
		return n(r.state, r.dispatch, r);
	}
	inline(e) {
		if (this.#t == null) throw y();
		let t = this.#t.get(bh);
		return e(t.state, t.dispatch, t);
	}
};
function Uh(e = "cmdKey") {
	return T((() => () => !1), e);
}
var Wh = T(new Hh(), "commands"), Gh = T([Fh], "commandsTimer"), Kh = E("CommandsReady"), qh = (e) => {
	let t = new Hh();
	return t.setCtx(e), e.inject(Wh, t).inject(Gh, [Fh]).record(Kh), async () => (await e.waitTimers(Gh), e.done(Kh), () => {
		e.remove(Wh).remove(Gh).clearTimer(Kh);
	});
};
vh(qh, { displayName: "Commands" });
function Jh(e) {
	return e.Backspace = Fl(Ul, il, sl, ul), e;
}
var Yh = class {
	constructor() {
		this.#e = null, this.#t = [], this.setCtx = (e) => {
			this.#e = e;
		}, this.add = (e) => (this.#t.push(e), () => {
			this.#t = this.#t.filter((t) => t !== e);
		}), this.addObjectKeymap = (e) => {
			let t = [];
			return Object.entries(e).forEach(([e, n]) => {
				if (typeof n == "function") {
					let r = {
						key: e,
						onRun: () => n
					};
					this.#t.push(r), t.push(() => {
						this.#t = this.#t.filter((e) => e !== r);
					});
				} else this.#t.push(n), t.push(() => {
					this.#t = this.#t.filter((e) => e !== n);
				});
			}), () => {
				t.forEach((e) => e());
			};
		}, this.addBaseKeymap = () => {
			let e = Jh(Bl);
			return this.addObjectKeymap(e);
		}, this.build = () => {
			let e = {};
			return this.#t.forEach((t) => {
				e[t.key] = [...e[t.key] || [], t];
			}), Object.fromEntries(Object.entries(e).map(([e, t]) => {
				let n = t.sort((e, t) => (t.priority ?? 50) - (e.priority ?? 50));
				return [e, (e, t, r) => {
					let i = this.#e;
					if (i == null) throw f();
					return Fl(...n.map((e) => e.onRun(i)))(e, t, r);
				}];
			}));
		};
	}
	#e;
	#t;
	get ctx() {
		return this.#e;
	}
}, Xh = T(new Yh(), "keymap"), Zh = T([Fh], "keymapTimer"), Qh = E("KeymapReady"), $h = (e) => {
	let t = new Yh();
	return t.setCtx(e), e.inject(Xh, t).inject(Zh, [Fh]).record(Qh), async () => (await e.waitTimers(Zh), e.done(Qh), () => {
		e.remove(Xh).remove(Zh).clearTimer(Qh);
	});
}, eg = E("ParserReady"), tg = (() => {
	throw f();
}), ng = T(tg, "parser"), rg = T([], "parserTimer"), ig = (e) => (e.inject(ng, tg).inject(rg, [Fh]).record(eg), async () => {
	await e.waitTimers(rg);
	let t = e.get(kh), n = e.get(Lh);
	return e.set(ng, Xu.create(n, t)), e.done(eg), () => {
		e.remove(ng).remove(rg).clearTimer(eg);
	};
});
vh(ig, { displayName: "Parser" });
var ag = E("SerializerReady"), og = T([], "serializerTimer"), sg = (() => {
	throw f();
}), cg = T(sg, "serializer"), lg = (e) => (e.inject(cg, sg).inject(og, [Fh]).record(ag), async () => {
	await e.waitTimers(og);
	let t = e.get(kh), n = e.get(Lh);
	return e.set(cg, $u.create(n, t)), e.done(ag), () => {
		e.remove(cg).remove(og).clearTimer(ag);
	};
});
vh(lg, { displayName: "Serializer" });
var ug = T("", "defaultValue"), dg = T((e) => e, "stateOptions"), fg = T([], "editorStateTimer"), pg = E("EditorStateReady");
function mg(e, t, n) {
	if (typeof e == "string") return t(e);
	if (e.type === "html") return $o.fromSchema(n).parse(e.dom);
	if (e.type === "json") return wo.fromJSON(n, e.value);
	throw l(e);
}
var hg = new rl("MILKDOWN_STATE_TRACKER"), gg = (e) => (e.inject(ug, "").inject(xh, {}).inject(dg, (e) => e).inject(fg, [
	eg,
	ag,
	Kh,
	Qh
]).record(pg), async () => {
	await e.waitTimers(fg);
	let t = e.get(Lh), n = e.get(ng), r = e.get(wh), i = e.get(dg), a = e.get(Th), o = mg(e.get(ug), n, t), s = e.get(Xh), c = s.addBaseKeymap(), l = [
		...a,
		new el({
			key: hg,
			state: {
				init: () => {},
				apply: (t, n, r, i) => {
					e.set(xh, i);
				}
			}
		}),
		mu({ rules: r }),
		Gu(s.build())
	];
	e.set(Th, l);
	let u = i({
		schema: t,
		doc: o,
		plugins: l
	}), d = Qc.create(u);
	return e.set(xh, d), e.done(pg), () => {
		c(), e.remove(ug).remove(xh).remove(dg).remove(fg).clearTimer(pg);
	};
});
vh(gg, { displayName: "EditorState" });
var _g = T([], "pasteRule"), vg = T([Fh], "pasteRuleTimer"), yg = E("PasteRuleReady"), bg = (e) => (e.inject(_g, []).inject(vg, [Fh]).record(yg), async () => (await e.waitTimers(vg), e.done(yg), () => {
	e.remove(_g).remove(vg).clearTimer(yg);
}));
vh(bg, { displayName: "PasteRule" });
var xg = E("EditorViewReady"), Sg = T([], "editorViewTimer"), Cg = T({}, "editorViewOptions"), wg = T(null, "root"), Tg = T(null, "rootDOM"), Eg = T({}, "rootAttrs");
function Dg(e, t) {
	let n = document.createElement("div");
	n.className = "milkdown", e.appendChild(n), t.set(Tg, n);
	let r = t.get(Eg);
	return Object.entries(r).forEach(([e, t]) => n.setAttribute(e, t)), n;
}
function Og(e) {
	e.classList.add("editor"), e.setAttribute("role", "textbox");
}
var kg = new rl("MILKDOWN_VIEW_CLEAR"), Ag = (e) => (e.inject(wg, document.body).inject(bh, {}).inject(Cg, {}).inject(Tg, null).inject(Eg, {}).inject(Sg, [pg, yg]).record(xg), async () => {
	await e.wait(Nh);
	let t = e.get(wg) || document.body, n = typeof t == "string" ? document.querySelector(t) : t;
	e.update(Th, (t) => [new el({
		key: kg,
		view: (t) => {
			let r = n ? Dg(n, e) : void 0;
			return (() => {
				if (r && n) {
					let e = t.dom;
					n.replaceChild(r, e), r.appendChild(e);
				}
			})(), { destroy: () => {
				r?.parentNode && r?.parentNode.replaceChild(t.dom, r), r?.remove();
			} };
		}
	}), ...t]), await e.waitTimers(Sg);
	let r = e.get(xh), i = e.get(Cg), a = new uh(n, {
		state: r,
		nodeViews: Object.fromEntries(e.get(Dh)),
		markViews: Object.fromEntries(e.get(Oh)),
		transformPasted: (t, n, r) => (e.get(_g).sort((e, t) => (t.priority ?? 50) - (e.priority ?? 50)).map((e) => e.run).forEach((e) => {
			t = e(t, n, r);
		}), t),
		...i
	});
	return Og(a.dom), e.set(bh, a), e.done(xg), () => {
		a?.destroy(), e.remove(wg).remove(bh).remove(Cg).remove(Tg).remove(Eg).remove(Sg).clearTimer(xg);
	};
});
vh(Ag, { displayName: "EditorView" });
var jg = /* @__PURE__ */ function(e) {
	return e.Idle = "Idle", e.OnCreate = "OnCreate", e.Created = "Created", e.OnDestroy = "OnDestroy", e.Destroyed = "Destroyed", e;
}({}), Mg = class e {
	constructor() {
		this.#e = !1, this.#t = "Idle", this.#n = [], this.#r = () => void 0, this.#i = new C(), this.#a = new se(), this.#o = /* @__PURE__ */ new Map(), this.#s = /* @__PURE__ */ new Map(), this.#c = new oe(this.#i, this.#a), this.#l = () => {
			let e = Mh(async (e) => {
				await Promise.all(this.#n.map((t) => Promise.resolve(t(e))));
			}), t = [
				Vh,
				ig,
				lg,
				qh,
				$h,
				bg,
				gg,
				Ag,
				Ph(this),
				e
			];
			this.#u(t, this.#s);
		}, this.#u = (e, t) => {
			e.forEach((e) => {
				let n = this.#c.produce(this.#e ? e.meta : void 0), r = e(n);
				t.set(e, {
					ctx: n,
					handler: r,
					cleanup: void 0
				});
			});
		}, this.#d = (e, t = !1) => Promise.all([e].flat().map(async (e) => {
			let n = this.#o.get(e)?.cleanup;
			return t ? this.#o.delete(e) : this.#o.set(e, {
				ctx: void 0,
				handler: void 0,
				cleanup: void 0
			}), typeof n == "function" ? n() : n;
		})), this.#f = async () => {
			await Promise.all([...this.#s.entries()].map(async ([e, { cleanup: t }]) => typeof t == "function" ? t() : t)), this.#s.clear();
		}, this.#p = (e) => {
			this.#t = e, this.#r(e);
		}, this.#m = (e) => [...e.entries()].map(async ([t, n]) => {
			let { ctx: r, handler: i } = n;
			if (!i) return;
			let a = await i();
			e.set(t, {
				ctx: r,
				handler: i,
				cleanup: a
			});
		}), this.enableInspector = (e = !0) => (this.#e = e, this), this.onStatusChange = (e) => (this.#r = e, this), this.config = (e) => (this.#n.push(e), this), this.removeConfig = (e) => (this.#n = this.#n.filter((t) => t !== e), this), this.use = (e) => {
			let t = [e].flat();
			return t.flat().forEach((e) => {
				this.#o.set(e, {
					ctx: void 0,
					handler: void 0,
					cleanup: void 0
				});
			}), this.#t === "Created" && this.#u(t, this.#o), this;
		}, this.remove = async (e) => this.#t === "OnCreate" ? (console.warn("[Milkdown]: You are trying to remove plugins when the editor is creating, this is not recommended, please check your code."), new Promise((t) => {
			setTimeout(() => {
				t(this.remove(e));
			}, 50);
		})) : (await this.#d([e].flat(), !0), this), this.create = async () => (this.#t === "OnCreate" || (this.#t === "Created" && await this.destroy(), this.#p("OnCreate"), this.#l(), this.#u([...this.#o.keys()], this.#o), await Promise.all([this.#m(this.#s), this.#m(this.#o)].flat()), this.#p("Created")), this), this.destroy = async (e = !1) => this.#t === "Destroyed" || this.#t === "OnDestroy" ? this : this.#t === "OnCreate" ? new Promise((t) => {
			setTimeout(() => {
				t(this.destroy(e));
			}, 50);
		}) : (e && (this.#n = []), this.#p("OnDestroy"), await this.#d([...this.#o.keys()], e), await this.#f(), this.#p("Destroyed"), this), this.action = (e) => e(this.#c), this.inspect = () => this.#e ? [...this.#s.values(), ...this.#o.values()].map(({ ctx: e }) => e?.inspector?.read()).filter((e) => !!e) : (console.warn("[Milkdown]: You are trying to collect inspection when inspector is disabled, please enable inspector by `editor.enableInspector()` first."), []);
	}
	static make() {
		return new e();
	}
	#e;
	#t;
	#n;
	#r;
	#i;
	#a;
	#o;
	#s;
	#c;
	#l;
	#u;
	#d;
	#f;
	#p;
	#m;
	get ctx() {
		return this.#c;
	}
	get status() {
		return this.#t;
	}
};
export { gl as $, T as $t, cg as A, Hi as At, Lu as B, M as Bt, ng as C, L as Ct, Ah as D, Yo as Dt, Eh as E, Go as Et, Gu as F, ur as Ft, vu as G, A as Gt, Ru as H, O as Ht, du as I, At as It, Vl as J, je as Jt, yu as K, j as Kt, ju as L, Xe as Lt, jm as M, ai as Mt, uh as N, oi as Nt, wg as O, R as Ot, Ku as P, kr as Pt, ol as Q, ge as Qt, zu as R, Ue as Rt, Rh as S, I as St, Th as T, So as Tt, Iu as U, Ee as Ut, Fu as V, D as Vt, Nu as W, k as Wt, Wl as X, be as Xt, Gl as Y, we as Yt, yl as Z, _e as Zt, wh as _, oc as _t, Nh as a, x as an, U as at, zh as b, $o as bt, ag as c, V as ct, ug as d, Fs as dt, E as en, jl as et, Ch as f, Ms as ft, mg as g, cc as gt, Cg as h, Nc as ht, xg as i, v as in, Qc as it, Om as j, ci as jt, Lh as k, ga as kt, Wh as l, Fc as lt, bh as m, Vs as mt, Mg as n, re as nn, Al as nt, Qh as o, b as on, el as ot, dg as p, Hs as pt, Au as q, Ae as qt, jg as r, ee as rn, Bc as rt, Fh as s, te as sn, rl as st, Kh as t, S as tn, Pl as tt, Uh as u, H as ut, Xh as v, Xs as vt, _g as w, wo as wt, Dh as x, ms as xt, Oh as y, Js as yt, Pu as z, He as zt };
