import React, { useState, useMemo } from "react";
import {
  BookOpen,
  Calendar,
  Target,
  Clock,
  Plus,
  Trash2,
  ChevronRight,
  ChevronLeft,
  CheckCircle2,
  Library,
  BookTueked,
  Hash,
} from "lucide-react";

const GOALS = [
  {
    id: "complete_books",
    title: "Finish one or more books",
    description: "Finish specific books within a set time frame.",
    icon: BookOpen,
  },
  {
    id: "more_pages",
    title: "Read more pages per session",
    description: "Increase your reading speed or depth.",
    icon: Library,
  },
  {
    id: "more_days",
    title: "Increase reading days",
    description: "Build a more frequent weekly habit.",
    icon: Calendar,
  },
  {
    id: "consistency",
    title: "Read more consistently",
    description: "Maintain a steady pace over a longer period.",
    icon: Target,
  },
];

const PAGE_RANGES = ["<100", "200~", "400~", "600~", "1000~"];

const PERIODS = [
  { id: "2w", label: "2 weeks", weeks: 2 },
  { id: "1m", label: "1 month", weeks: 4 },
  { id: "3m", label: "3 months", weeks: 12 },
  { id: "custom", label: "Custom", weeks: 0 },
];

const WEEK_DAYS = [
  { id: 0, label: "Mon" },
  { id: 1, label: "Tue" },
  { id: 2, label: "Wed" },
  { id: 3, label: "Thu" },
  { id: 4, label: "Fri" },
  { id: 5, label: "Sat" },
  { id: 6, label: "Sun" },
];

const parseFirstNumber = (value) => {
  if (!value) return 0;
  const match = String(value).match(/\d+/);
  return match ? parseInt(match[0], 10) : 0;
};

export default function ReadingPlanApp({ onClose }) {
  const [mainStep, setMainStep] = useState(1);
  const [baselineIndex, setBaselineIndex] = useState(0);
  const [subStep, setSubStep] = useState(0);

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const [formData, setFormData] = useState({
    goals: [],
    baselines: {},
    books: [],
    availability: {
      days: [],
      sessionType: "medium",
    },
    period: null,
    customWeeks: "",
  });

  const [newBook, setNewBook] = useState({ title: "", pages: "" });
  const [showSummary, setShowSummary] = useState(false);

  const selectedGoals = formData.goals;

  const handleNext = () => {
    if (mainStep === 1) {
      setMainStep(2);
      setBaselineIndex(0);
      setSubStep(0);
    } else if (mainStep === 2) {
      const currentGoalId = selectedGoals[baselineIndex];

      if (
        currentGoalId === "complete_books" &&
        subStep === 0 &&
        parseInt(formData.baselines["complete_books"]?.value, 10) > 0
      ) {
        setSubStep(1);
      } else {
        if (baselineIndex < selectedGoals.length - 1) {
          setBaselineIndex(baselineIndex + 1);
          setSubStep(0);
        } else {
          setMainStep(3);
        }
      }
    } else if (mainStep < 5) {
      setMainStep(mainStep + 1);
    } else {
      setShowSummary(true);
    }
  };

  const handleBack = () => {
    if (mainStep === 2) {
      if (subStep === 1) {
        setSubStep(0);
      } else if (baselineIndex > 0) {
        setBaselineIndex(baselineIndex - 1);
        const prevGoalId = selectedGoals[baselineIndex - 1];
        setSubStep(prevGoalId === "complete_books" ? 1 : 0);
      } else {
        setMainStep(1);
      }
    } else if (mainStep > 1) {
      setMainStep(mainStep - 1);
    }
  };

  const handleToggleGoal = (goalId) => {
    setFormData((prev) => {
      const isSelected = prev.goals.includes(goalId);
      const newGoals = isSelected
        ? prev.goals.filter((id) => id !== goalId)
        : [...prev.goals, goalId];
      return { ...prev, goals: newGoals };
    });
  };

  const setBaselineValue = (goalId, value) => {
    setFormData((prev) => ({
      ...prev,
      baselines: {
        ...prev.baselines,
        [goalId]: { ...prev.baselines[goalId], value },
      },
    }));
  };

  const setBookDetailSize = (index, range) => {
    setFormData((prev) => {
      const details = [...(prev.baselines["complete_books"]?.details || [])];
      details[index] = range;
      return {
        ...prev,
        baselines: {
          ...prev.baselines,
          complete_books: { ...prev.baselines["complete_books"], details },
        },
      };
    });
  };

  const handleAddBook = () => {
    if (newBook.title && newBook.pages) {
      setFormData((prev) => ({
        ...prev,
        books: [
          ...prev.books,
          { ...newBook, id: Date.now(), pages: parseInt(newBook.pages, 10) },
        ],
      }));
      setNewBook({ title: "", pages: "" });
    }
  };

  const removeBook = (bookId) => {
    setFormData((prev) => ({
      ...prev,
      books: prev.books.filter((book) => book.id !== bookId),
    }));
  };

  const proposal = useMemo(() => {
    const totalPages = formData.books.reduce((acc, b) => acc + b.pages, 0);
    const weeks = formData.period === "custom"
      ? parseInt(formData.customWeeks, 10) || 0
      : PERIODS.find((p) => p.id === formData.period)?.weeks || 0;

    if (
      totalPages === 0 ||
      weeks === 0 ||
      formData.availability.days.length === 0
    ) {
      return null;
    }

    const totalSessions = weeks * formData.availability.days.length;
    const pagesPerSession = Math.ceil(totalPages / totalSessions);

    return { totalPages, weeks, pagesPerSession, totalSessions };
  }, [formData]);

  const buildPayload = () => {
    const periodValue = formData.period === "custom" && formData.customWeeks
      ? `custom:${formData.customWeeks}`
      : formData.period || "custom";

    const baselineMetrics = {};
    Object.keys(formData.baselines || {}).forEach((key) => {
      const value = formData.baselines[key]?.value;
      if (value !== undefined && value !== null && value !== "") {
        baselineMetrics[key] = String(value);
      }
    });

    const goalsPayload = formData.goals.map((goalId) => {
      let targetValue = parseFirstNumber(formData.baselines[goalId]?.value);

      if (goalId === "more_days" && formData.availability.days.length > 0) {
        targetValue = formData.availability.days.length;
      }

      if (goalId === "more_pages" && proposal?.pagesPerSession) {
        targetValue = proposal.pagesPerSession;
      }

      targetValue = Math.max(1, targetValue || 1);

      return {
        goal_kind: goalId,
        metric: goalId,
        target_value: targetValue,
        period: periodValue,
      };
    });

    return {
      goals: goalsPayload,
      baselines: baselineMetrics,
      books: formData.books,
      availability: formData.availability,
      period: {
        id: formData.period,
        customWeeks: formData.customWeeks || null,
      },
      planned_sessions: [],
    };
  };

  const handleSubmit = async () => {
    if (submitting) {
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const restUrl = window.PoliteiaReadingPlan?.restUrl;
      const nonce = window.PoliteiaReadingPlan?.nonce;
      if (!restUrl || !nonce) {
        throw new Error("Plan creation failed");
      }

      const payload = buildPayload();

      const res = await fetch(restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": nonce,
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Plan creation failed");
      }

      if (typeof onClose === "function") {
        onClose();
      }
      window.alert("Your reading plan has started");
    } catch (err) {
      setError(err?.message || "Plan creation failed");
    } finally {
      setSubmitting(false);
    }
  };

  const renderStepContent = () => {
    if (mainStep === 1) {
      return (
        <div className="space-y-6 animate-in fade-in duration-500">
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-slate-800">
              Which aspects of your reading would you like to improve?
            </h2>
            <p className="text-slate-500">You can select multiple goals</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {GOALS.map((goal) => (
              <button
                key={goal.id}
                onClick={() => handleToggleGoal(goal.id)}
                className={`p-4 text-left border-2 rounded-2xl transition-all ${
                  formData.goals.includes(goal.id)
                    ? "border-indigo-600 bg-indigo-50 ring-4 ring-indigo-50"
                    : "border-slate-100 hover:border-indigo-200"
                }`}
              >
                <goal.icon
                  className={`w-8 h-8 mb-3 ${
                    formData.goals.includes(goal.id)
                      ? "text-indigo-600"
                      : "text-slate-400"
                  }`}
                />
                <h3 className="font-bold text-slate-800">{goal.title}</h3>
                <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                  {goal.description}
                </p>
              </button>
            ))}
          </div>
        </div>
      );
    }

    if (mainStep === 2) {
      const currentGoalId = selectedGoals[baselineIndex];
      const goalTitle = GOALS.find((g) => g.id === currentGoalId)?.title;

      if (currentGoalId === "complete_books" && subStep === 0) {
        return (
          <div className="space-y-8 animate-in slide-in-from-right-8 duration-300">
            <div className="text-center">
              <span className="text-indigo-600 font-bold text-xs uppercase tracking-widest">
                {goalTitle}
              </span>
              <h2 className="text-2xl font-bold text-slate-800 mt-2">
                How many books did you finish in the last year?
              </h2>
            </div>
            <div className="grid grid-cols-5 gap-3">
              {["0", "1", "2", "3", "4+"].map((num) => (
                <button
                  key={num}
                  onClick={() => setBaselineValue("complete_books", num)}
                  className={`aspect-square rounded-2xl border-2 font-bold text-xl transition-all ${
                    formData.baselines["complete_books"]?.value === num
                      ? "bg-indigo-600 border-indigo-600 text-white shadow-lg"
                      : "border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300"
                  }`}
                >
                  {num}
                </button>
              ))}
            </div>
          </div>
        );
      }

      if (currentGoalId === "complete_books" && subStep === 1) {
        const count = parseInt(formData.baselines["complete_books"]?.value, 10) || 0;
        const slots = count === 4 ? 4 : count;
        return (
          <div className="space-y-6 animate-in slide-in-from-right-8 duration-300">
            <div className="text-center">
              <h2 className="text-xl font-bold text-slate-800">
                How long were those books?
              </h2>
              <p className="text-slate-500 text-sm">
                Select the page range for each one
              </p>
            </div>
            <div className="space-y-4 max-h-[350px] overflow-y-auto pr-2">
              {[...Array(slots)].map((_, i) => (
                <div
                  key={i}
                  className="p-4 bg-slate-50 rounded-2xl border border-slate-100"
                >
                  <p className="text-xs font-bold text-slate-400 uppercase mb-3">
                    Book #{i + 1}
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {PAGE_RANGES.map((range) => (
                      <button
                        key={range}
                        onClick={() => setBookDetailSize(i, range)}
                        className={`px-3 py-2 rounded-lg text-xs font-bold border-2 transition-all ${
                          formData.baselines["complete_books"]?.details?.[i] === range
                            ? "bg-indigo-100 border-indigo-600 text-indigo-700"
                            : "bg-white border-slate-200 text-slate-500"
                        }`}
                      >
                        {range}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        );
      }

      const otherQuestions = {
        more_pages: {
          q: "In a typical session, how many pages do you usually read?",
          opts: ["<10", "10–20", "20–40", "40+"],
        },
        more_days: {
          q: "How many days per week do you currently read?",
          opts: ["0–1", "2–3", "4–5", "6–7"],
        },
        consistency: {
          q: "How many weeks did you read at least once in the last month?",
          opts: ["0", "1", "2", "3", "4"],
        },
      };
      const config = otherQuestions[currentGoalId];

      return (
        <div className="space-y-8 animate-in slide-in-from-right-8 duration-300">
          <div className="text-center">
            <span className="text-indigo-600 font-bold text-xs uppercase tracking-widest">
              {goalTitle}
            </span>
            <h2 className="text-2xl font-bold text-slate-800 mt-2">
              {config.q}
            </h2>
          </div>
          <div className="grid grid-cols-2 gap-3">
            {config.opts.map((opt) => (
              <button
                key={opt}
                onClick={() => setBaselineValue(currentGoalId, opt)}
                className={`p-4 rounded-2xl border-2 font-bold transition-all ${
                  formData.baselines[currentGoalId]?.value === opt
                    ? "bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-100"
                    : "border-slate-100 bg-slate-50 text-slate-500 hover:border-slate-300"
                }`}
              >
                {opt}
              </button>
            ))}
          </div>
        </div>
      );
    }

    if (mainStep === 3) {
      return (
        <div className="space-y-6 animate-in slide-in-from-right-8 duration-300">
          <div className="text-center mb-6">
            <h2 className="text-2xl font-bold text-slate-800">
              Which books will you read?
            </h2>
            <p className="text-slate-500">
              Add the titles from your personal library
            </p>
          </div>

          <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
            <input
              type="text"
              placeholder="Book title"
              className="w-full p-3 border rounded-xl focus:ring-2 ring-indigo-500 outline-none text-sm"
              value={newBook.title}
              onChange={(e) => setNewBook({ ...newBook, title: e.target.value })}
            />
            <div className="flex gap-2">
              <input
                type="number"
                placeholder="Pages"
                className="flex-1 p-3 border rounded-xl focus:ring-2 ring-indigo-500 outline-none text-sm"
                value={newBook.pages}
                onChange={(e) =>
                  setNewBook({ ...newBook, pages: e.target.value })
                }
              />
              <button
                onClick={handleAddBook}
                className="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 flex items-center justify-center gap-2 text-sm font-bold shadow-lg shadow-indigo-100"
              >
                <Plus className="w-4 h-4" /> Add
              </button>
            </div>
          </div>

          <div className="space-y-2 max-h-40 overflow-y-auto pr-2">
            {formData.books.map((book) => (
              <div
                key={book.id}
                className="flex items-center justify-between p-3 bg-white border rounded-xl shadow-sm"
              >
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                    <BookTueked className="text-indigo-500 w-4 h-4" />
                  </div>
                  <div>
                    <p className="text-sm font-bold text-slate-800">
                      {book.title}
                    </p>
                    <p className="text-[10px] text-slate-400 font-bold uppercase tracking-tight">
                      {book.pages} pages
                    </p>
                  </div>
                </div>
                <button
                  onClick={() => removeBook(book.id)}
                  className="text-rose-400 hover:bg-rose-50 p-2 rounded-full transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (mainStep === 4) {
      return (
        <div className="space-y-8 animate-in slide-in-from-right-8 duration-300">
          <div className="text-center mb-4">
            <h2 className="text-2xl font-bold text-slate-800">
              Your availability
            </h2>
            <p className="text-slate-500">
              When do you have time to immerse yourself in reading?
            </p>
          </div>

          <div className="space-y-4">
            <label className="block text-xs font-black text-slate-400 uppercase tracking-widest">
              Available days:
            </label>
            <div className="flex justify-between gap-1">
              {WEEK_DAYS.map((day) => (
                <button
                  key={day.id}
                  onClick={() => {
                    const days = formData.availability.days.includes(day.id)
                      ? formData.availability.days.filter((d) => d !== day.id)
                      : [...formData.availability.days, day.id];
                    setFormData({
                      ...formData,
                      availability: { ...formData.availability, days },
                    });
                  }}
                  className={`w-10 h-10 md:w-12 md:h-12 rounded-xl font-bold transition-all border-2 text-sm ${
                    formData.availability.days.includes(day.id)
                      ? "bg-indigo-600 border-indigo-600 text-white shadow-lg"
                      : "border-slate-100 text-slate-400 hover:border-indigo-300 bg-slate-50"
                  }`}
                >
                  {day.label}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-4">
            <label className="block text-xs font-black text-slate-400 uppercase tracking-widest">
              Session pace:
            </label>
            <div className="grid grid-cols-3 gap-3">
              {["short", "medium", "long"].map((type) => (
                <button
                  key={type}
                  onClick={() =>
                    setFormData({
                      ...formData,
                      availability: {
                        ...formData.availability,
                        sessionType: type,
                      },
                    })
                  }
                  className={`py-4 rounded-xl border-2 text-xs font-bold transition-all ${
                    formData.availability.sessionType === type
                      ? "border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm"
                      : "border-slate-100 text-slate-500 bg-slate-50"
                  }`}
                >
                  {type === "short"
                    ? "Sprints"
                    : type === "medium"
                    ? "Balanced"
                    : "Immersion"}
                </button>
              ))}
            </div>
          </div>
        </div>
      );
    }

    if (mainStep === 5) {
      return (
        <div className="space-y-6 animate-in slide-in-from-right-8 duration-300">
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-slate-800">
              Plan horizon
            </h2>
            <p className="text-slate-500">
              How long do you want to take to reach this goal?
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            {PERIODS.map((p) => (
              <button
                key={p.id}
                onClick={() => setFormData({ ...formData, period: p.id })}
                className={`p-5 rounded-2xl border-2 text-center transition-all ${
                  formData.period === p.id
                    ? "border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100"
                    : "border-slate-100 text-slate-600 hover:border-indigo-200 bg-slate-50"
                }`}
              >
                <span className="block text-sm font-black uppercase tracking-tight">
                  {p.label}
                </span>
              </button>
            ))}
          </div>

          {formData.period === "custom" && (
            <div className="mt-4 animate-in slide-in-from-top-4">
              <input
                type="number"
                className="w-full p-4 border rounded-2xl focus:ring-2 ring-indigo-500 outline-none text-center font-bold"
                placeholder="Enter number of weeks"
                value={formData.customWeeks}
                onChange={(e) =>
                  setFormData({ ...formData, customWeeks: e.target.value })
                }
              />
            </div>
          )}
        </div>
      );
    }

    return null;
  };

  const isStepValid = () => {
    if (mainStep === 1) return formData.goals.length > 0;
    if (mainStep === 2) {
      const currentGoalId = selectedGoals[baselineIndex];
      const baseline = formData.baselines[currentGoalId];
      if (!baseline?.value) return false;
      if (currentGoalId === "complete_books" && subStep === 1) {
        const count = parseInt(baseline.value, 10) || 0;
        const slots = count === 4 ? 4 : count;
        return (
          baseline.details?.length >= slots && baseline.details.every((d) => d)
        );
      }
      return true;
    }
    if (mainStep === 3) return formData.books.length > 0;
    if (mainStep === 4) return formData.availability.days.length > 0;
    if (mainStep === 5)
      return (
        formData.period && (formData.period !== "custom" || formData.customWeeks)
      );
    return true;
  };

  if (showSummary) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4 font-sans">
        <div className="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-white">
          <div className="bg-indigo-600 p-10 text-white relative overflow-hidden">
            <div className="absolute top-0 right-0 p-8 opacity-10">
              <CheckCircle2 className="w-48 h-48" />
            </div>
            <h1 className="text-4xl font-black tracking-tight">Your Roadmap</h1>
            <p className="text-indigo-100 mt-2 text-lg font-medium">
              A plan designed for your current pace.
            </p>
          </div>

          <div className="p-10 space-y-10">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-6 bg-indigo-50 rounded-3xl text-center">
                <span className="text-[10px] text-indigo-400 uppercase font-black tracking-widest">
                  Daily Load
                </span>
                <p className="text-3xl font-black text-slate-800 mt-2">
                  {proposal?.pagesPerSession}
                </p>
                <p className="text-[10px] font-bold text-indigo-300 uppercase">
                  pages / session
                </p>
              </div>
              <div className="p-6 bg-slate-50 rounded-3xl text-center">
                <span className="text-[10px] text-slate-400 uppercase font-black tracking-widest">
                  Commitment
                </span>
                <p className="text-3xl font-black text-slate-800 mt-2">
                  {formData.availability.days.length}
                </p>
                <p className="text-[10px] font-bold text-slate-400 uppercase">
                  days / week
                </p>
              </div>
              <div className="p-6 bg-slate-50 rounded-3xl text-center">
                <span className="text-[10px] text-slate-400 uppercase font-black tracking-widest">
                  Goal
                </span>
                <p className="text-3xl font-black text-slate-800 mt-2">
                  {proposal?.totalPages}
                </p>
                <p className="text-[10px] font-bold text-slate-400 uppercase">
                  total pages
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-10">
              <div className="space-y-4">
                <h3 className="font-black text-slate-800 flex items-center gap-2 uppercase text-xs tracking-widest">
                  <Clock className="w-4 h-4 text-indigo-500" /> Final Schedule
                </h3>
                <div className="space-y-3 bg-slate-50 p-6 rounded-3xl">
                  <div className="flex justify-between items-center text-sm">
                    <span className="text-slate-500 font-bold">Duration</span>
                    <span className="font-black text-slate-800">
                      {proposal?.weeks} weeks
                    </span>
                  </div>
                  <div className="flex justify-between items-center text-sm pt-2 border-t border-slate-200">
                    <span>Total sessions</span>
                    <span className="font-black text-slate-800">
                      {proposal?.totalSessions}
                    </span>
                  </div>
                </div>
              </div>

              <div className="space-y-4">
                <h3 className="font-black text-slate-800 flex items-center gap-2 uppercase text-xs tracking-widest">
                  <Hash className="w-4 h-4 text-indigo-500" /> Your starting point
                </h3>
                <div className="space-y-2">
                  {selectedGoals.map((g) => (
                    <div
                      key={g}
                      className="text-[10px] bg-slate-100 px-4 py-2 rounded-xl flex justify-between items-center"
                    >
                      <span className="text-slate-500 font-black uppercase tracking-tighter">
                        {GOALS.find((item) => item.id === g).title}
                      </span>
                      <span className="text-indigo-600 font-black">
                        {formData.baselines[g]?.value || "-"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {error ? (
              <div className="text-sm text-rose-600 font-semibold">{error}</div>
            ) : null}

            <div className="flex flex-col md:flex-row gap-4 pt-6">
              <button
                onClick={() => setShowSummary(false)}
                className="flex-1 py-5 rounded-2xl border-2 border-slate-200 text-slate-500 font-black uppercase text-xs tracking-widest hover:bg-slate-50 transition-colors"
              >
                Adjust Details
              </button>
              <button
                onClick={handleSubmit}
                disabled={submitting}
                className="flex-1 py-5 rounded-2xl bg-indigo-600 text-white font-black uppercase text-xs tracking-widest hover:bg-indigo-700 shadow-xl shadow-indigo-100 disabled:opacity-60 disabled:cursor-not-allowed"
              >
                {submitting ? "Creating plan…" : "Start My Plan"}
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4 font-sans text-slate-900">
      <div className="bg-white w-full max-w-xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-100 flex flex-col">
        <div className="bg-slate-100 h-1.5 flex">
          {[1, 2, 3, 4, 5].map((s) => (
            <div
              key={s}
              className={`flex-1 transition-all duration-700 ${
                s <= mainStep ? "bg-indigo-500" : "bg-slate-200"
              }`}
            />
          ))}
        </div>

        <div className="p-10 flex-1 flex flex-col">
          <div className="flex justify-between items-center mb-10">
            <span className="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-black uppercase tracking-[0.2em]">
              Section {mainStep} of 5
            </span>
            {(mainStep > 1 || (mainStep === 2 && (baselineIndex > 0 || subStep > 0))) && (
              <button
                onClick={handleBack}
                className="text-slate-400 hover:text-indigo-600 flex items-center gap-1 text-[10px] font-black uppercase tracking-widest transition-colors"
              >
                <ChevronLeft className="w-3 h-3" /> Back
              </button>
            )}
          </div>

          <div className="flex-1">{renderStepContent()}</div>

          <div className="mt-12 flex justify-end">
            <button
              disabled={!isStepValid()}
              onClick={handleNext}
              className="w-full md:w-auto bg-indigo-600 text-white px-12 py-5 rounded-2xl font-black flex items-center justify-center gap-3 hover:bg-indigo-700 transition-all disabled:opacity-20 disabled:grayscale shadow-2xl shadow-indigo-100 uppercase text-[10px] tracking-[0.2em]"
            >
              {mainStep === 5 ? "Create My Plan" : "Next"}
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
