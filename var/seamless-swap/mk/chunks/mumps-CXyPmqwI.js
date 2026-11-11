// node_modules/.pnpm/@codemirror+legacy-modes@6.5.5/node_modules/@codemirror/legacy-modes/mode/mumps.js
function e(e) {
	return RegExp("^((" + e.join(")|(") + "))\\b", "i");
}
var t = /* @__PURE__ */ RegExp("^[\\+\\-\\*/&#!_?\\\\<>=\\'\\[\\]]"), n = /* @__PURE__ */ RegExp("^(('=)|(<=)|(>=)|('>)|('<)|([[)|(]])|(^$))"), r = /* @__PURE__ */ RegExp("^[\\.,:]"), i = /* @__PURE__ */ RegExp("[()]"), a = /* @__PURE__ */ RegExp("^[%A-Za-z][A-Za-z0-9]*"), o = /* @__PURE__ */ "break.close.do.else.for.goto.halt.hang.if.job.kill.lock.merge.new.open.quit.read.set.tcommit.trollback.tstart.use.view.write.xecute.b.c.d.e.f.g.h.i.j.k.l.m.n.o.q.r.s.tc.tro.ts.u.v.w.x".split("."), s = e(/* @__PURE__ */ "\\$ascii.\\$char.\\$data.\\$ecode.\\$estack.\\$etrap.\\$extract.\\$find.\\$fnumber.\\$get.\\$horolog.\\$io.\\$increment.\\$job.\\$justify.\\$length.\\$name.\\$next.\\$order.\\$piece.\\$qlength.\\$qsubscript.\\$query.\\$quit.\\$random.\\$reverse.\\$select.\\$stack.\\$test.\\$text.\\$translate.\\$view.\\$x.\\$y.\\$a.\\$c.\\$d.\\$e.\\$ec.\\$es.\\$et.\\$f.\\$fn.\\$g.\\$h.\\$i.\\$j.\\$l.\\$n.\\$na.\\$o.\\$p.\\$q.\\$ql.\\$qs.\\$r.\\$re.\\$s.\\$st.\\$t.\\$tr.\\$v.\\$z".split(".")), c = e(o);
function l(e, o) {
	e.sol() && (o.label = !0, o.commandMode = 0);
	var l = e.peek();
	return l == " " || l == "	" ? (o.label = !1, o.commandMode == 0 ? o.commandMode = 1 : (o.commandMode < 0 || o.commandMode == 2) && (o.commandMode = 0)) : l != "." && o.commandMode > 0 && (o.commandMode = l == ":" ? -1 : 2), (l === "(" || l === "	") && (o.label = !1), l === ";" ? (e.skipToEnd(), "comment") : e.match(/^[-+]?\d+(\.\d+)?([eE][-+]?\d+)?/) ? "number" : l == "\"" ? e.skipTo("\"") ? (e.next(), "string") : (e.skipToEnd(), "error") : e.match(n) || e.match(t) ? "operator" : e.match(r) ? null : i.test(l) ? (e.next(), "bracket") : o.commandMode > 0 && e.match(c) ? "controlKeyword" : e.match(s) ? "builtin" : e.match(a) ? "variable" : l === "$" || l === "^" ? (e.next(), "builtin") : l === "@" ? (e.next(), "string.special") : /[\w%]/.test(l) ? (e.eatWhile(/[\w%]/), "variable") : (e.next(), "error");
}
var u = {
	name: "mumps",
	startState: function() {
		return {
			label: !1,
			commandMode: 0
		};
	},
	token: function(e, t) {
		var n = l(e, t);
		return t.label ? "tag" : n;
	}
};
export { u as mumps };
