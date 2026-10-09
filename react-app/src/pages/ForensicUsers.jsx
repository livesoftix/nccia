import { useState, useEffect, useCallback } from "react";
import api from "../api";
import ConfirmModal from "../components/ConfirmModal";
import LoadingSkeleton from "../components/LoadingSkeleton";

const ROLE_LABELS = {
    admin_forensic: "Admin Forensic",
    dd_forensic: "DD Forensic",
    ad_forensic: "AD Forensic",
};

const ROLE_OPTIONS = Object.entries(ROLE_LABELS).map(([value, label]) => ({
    value,
    label,
}));

const EMPTY_FORM = {
    name: "",
    email: "",
    role: "ad_forensic",
    designation: "",
    password: "",
    generate_password: true,
};

export default function ForensicUsers() {
    const [list, setList] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [search, setSearch] = useState("");
    const [modal, setModal] = useState(null);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState(null);
    const [creds, setCreds] = useState(null);
    const [resetTarget, setResetTarget] = useState(null);
    const [resetPwd, setResetPwd] = useState("");
    const [savingReset, setSavingReset] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [genTarget, setGenTarget] = useState(null);
    const [savingGen, setSavingGen] = useState(false);

    const fetchData = useCallback(
        (p = page) => {
            setLoading(true);
            api.get("/forensic/users", { params: { page: p } })
                .then((r) => {
                    const d = r.data.data || r.data;
                    if (Array.isArray(d)) {
                        setList(d);
                    } else if (d?.data) {
                        setList(d.data);
                        setLastPage(d.last_page || 1);
                        setPage(d.current_page || 1);
                    } else {
                        setList([]);
                    }
                })
                .catch(() => {})
                .finally(() => setLoading(false));
        },
        [page],
    );

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const setF = (key) => (e) => {
        const value =
            e.target?.type === "checkbox" ? e.target.checked : e.target.value;
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const openCreate = () => {
        setEditing(null);
        setForm({ ...EMPTY_FORM });
        setFormError(null);
        setModal("create");
    };

    const openEdit = (u) => {
        setEditing(u);
        setForm({
            name: u.name || "",
            email: u.email || "",
            role: u.roles?.[0]?.name || "ad_forensic",
            designation: u.designation || "",
            password: "",
            generate_password: false,
        });
        setFormError(null);
        setModal("edit");
    };

    const closeModal = () => {
        setModal(null);
        setEditing(null);
        setFormError(null);
    };

    const handleSave = async () => {
        if (form.password && ([...form.password].length < 15 || [...form.password].length > 128)) {
            setFormError("Use a password of 15–128 characters.");
            return;
        }
        setSaving(true);
        setFormError(null);
        try {
            const payload = {
                name: form.name,
                email: form.email,
                role: form.role,
                designation: form.designation || null,
                ...(form.password ? { password: form.password } : {}),
            };
            if (
                modal === "create" &&
                form.generate_password &&
                !form.password
            ) {
                payload.generate_password = true;
            }

            let r;
            if (modal === "create") {
                r = await api.post("/forensic/users", payload);
            } else {
                r = await api.put(`/forensic/users/${editing.id}`, payload);
            }

            if (r.data.credentials) {
                setCreds(r.data.credentials);
            } else {
                alert(r.data.message || "Saved successfully");
            }
            closeModal();
            fetchData();
        } catch (err) {
            const msg =
                err.response?.data?.message ||
                err.response?.data?.errors?.email?.[0] ||
                err.message ||
                "Failed to save user";
            setFormError(msg);
        } finally {
            setSaving(false);
        }
    };

    const handleResetPassword = async () => {
        if (!resetTarget || !resetPwd) return;
        if ([...resetPwd].length < 15 || [...resetPwd].length > 128) {
            alert("Use a password of 15–128 characters.");
            return;
        }
        setSavingReset(true);
        try {
            await api.post(`/forensic/users/${resetTarget.id}/reset-password`, {
                password: resetPwd,
            });
            alert(`Password reset successfully for ${resetTarget.name}`);
            setResetTarget(null);
            setResetPwd("");
        } catch (err) {
            alert(err.response?.data?.message || "Failed to reset password");
        } finally {
            setSavingReset(false);
        }
    };

    const handleGenerate = async () => {
        if (!genTarget) return;
        setSavingGen(true);
        try {
            const r = await api.post(
                `/forensic/users/${genTarget.id}/generate-password`,
            );
            setCreds(r.data.credentials);
            setGenTarget(null);
        } catch (err) {
            alert(
                err.response?.data?.message || "Failed to generate credentials",
            );
        } finally {
            setSavingGen(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        try {
            await api.delete(`/forensic/users/${deleteTarget.id}`);
            setList(list.filter((u) => u.id !== deleteTarget.id));
            setDeleteTarget(null);
        } catch (err) {
            alert(err.response?.data?.message || "Failed to delete user");
        }
    };

    const filteredList = list.filter(
        (u) =>
            u.name?.toLowerCase().includes(search.toLowerCase()) ||
            u.email?.toLowerCase().includes(search.toLowerCase()),
    );

    if (loading)
        return (
            <div className="page-content">
                <LoadingSkeleton type="table" columns={5} rows={10} />
            </div>
        );

    return (
        <div className="page-content">
            <div className="page-header">
                <div className="page-title-group">
                    <h1 className="page-title">Forensic Users</h1>
                    <p className="page-subtitle">
                        Manage forensic portal users &amp; issue login
                        credentials
                    </p>
                    <div className="title-underline"></div>
                </div>
                <div className="page-actions">
                    <button
                        onClick={openCreate}
                        className="btn btn-primary btn-sm"
                    >
                        <svg
                            width="12"
                            height="12"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                        >
                            <path d="M12 5v14" />
                            <path d="M5 12h14" />
                        </svg>{" "}
                        Add Forensic User
                    </button>
                </div>
            </div>

            <div className="card">
                <div className="card-header">
                    <div className="card-title">
                        <div
                            className="card-icon"
                            style={{
                                background: "rgba(0,188,212,0.14)",
                                color: "#0097a7",
                            }}
                        >
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                            >
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                            </svg>
                        </div>
                        All Forensic Users
                    </div>
                    <div className="section-actions">
                        <input
                            type="text"
                            className="filter-select"
                            placeholder="Search name or email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{
                                height: "32px",
                                padding: "0 10px",
                                fontSize: 12,
                            }}
                        />
                        <span
                            style={{
                                fontSize: "11.5px",
                                color: "#6c757d",
                                marginLeft: 8,
                            }}
                        >
                            Total:{" "}
                            <strong style={{ color: "#2b2b2b" }}>
                                {filteredList.length}
                            </strong>
                        </span>
                    </div>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                    <div className="table-responsive">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Designation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredList.map((u, i) => (
                                    <tr key={u.id}>
                                        <td>
                                            <span className="table-id">
                                                #{u.id}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                style={{
                                                    fontSize: "13px",
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {u.name}
                                            </span>
                                        </td>
                                        <td>{u.email}</td>
                                        <td>
                                            <span
                                                className="badge badge-active"
                                                style={{
                                                    background:
                                                        "rgba(0,188,212,0.14)",
                                                    color: "#0097a7",
                                                }}
                                            >
                                                {u.roles?.[0]?.name
                                                    ? ROLE_LABELS[
                                                          u.roles[0].name
                                                      ] || u.roles[0].name
                                                    : "No Role"}
                                            </span>
                                        </td>
                                        <td>{u.designation || "-"}</td>
                                        <td>
                                            <div
                                                style={{
                                                    display: "flex",
                                                    gap: "6px",
                                                    flexWrap: "wrap",
                                                }}
                                            >
                                                <button
                                                    onClick={() => openEdit(u)}
                                                    className="btn btn-outline btn-sm btn-icon"
                                                    title="Edit"
                                                >
                                                    <svg
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                    >
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setGenTarget(u)
                                                    }
                                                    className="btn btn-sm"
                                                    title="Generate credentials"
                                                    style={{
                                                        background:
                                                            "rgba(0,188,212,0.12)",
                                                        color: "#0097a7",
                                                        border: "none",
                                                        borderRadius: "8px",
                                                        width: "36px",
                                                        height: "36px",
                                                        display: "inline-flex",
                                                        alignItems: "center",
                                                        justifyContent:
                                                            "center",
                                                        cursor: "pointer",
                                                    }}
                                                >
                                                    <svg
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                    >
                                                        <rect
                                                            x="3"
                                                            y="11"
                                                            width="18"
                                                            height="11"
                                                            rx="2"
                                                        />
                                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setResetTarget(u);
                                                        setResetPwd("");
                                                    }}
                                                    className="btn btn-sm"
                                                    title="Reset Password"
                                                    style={{
                                                        background:
                                                            "rgba(37,99,235,0.12)",
                                                        color: "#2563eb",
                                                        border: "none",
                                                        borderRadius: "8px",
                                                        width: "36px",
                                                        height: "36px",
                                                        display: "inline-flex",
                                                        alignItems: "center",
                                                        justifyContent:
                                                            "center",
                                                        cursor: "pointer",
                                                    }}
                                                >
                                                    <svg
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                    >
                                                        <path d="M1 4v6h6" />
                                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setDeleteTarget(u)
                                                    }
                                                    className="btn btn-sm"
                                                    style={{
                                                        background:
                                                            "rgba(229,62,62,0.15)",
                                                        color: "#e53e3e",
                                                        border: "none",
                                                        borderRadius: "8px",
                                                        width: "36px",
                                                        height: "36px",
                                                        display: "inline-flex",
                                                        alignItems: "center",
                                                        justifyContent:
                                                            "center",
                                                        cursor: "pointer",
                                                    }}
                                                    title="Delete"
                                                >
                                                    <svg
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                    >
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {filteredList.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            style={{
                                                textAlign: "center",
                                                padding: "24px",
                                                color: "#6c757d",
                                            }}
                                        >
                                            No forensic users found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                {lastPage > 1 && (
                    <div
                        style={{
                            display: "flex",
                            justifyContent: "center",
                            alignItems: "center",
                            gap: 8,
                            padding: "12px 16px",
                            borderTop: "1px solid #f0f0f0",
                        }}
                    >
                        <button
                            disabled={page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            style={{
                                padding: "6px 14px",
                                borderRadius: 6,
                                border: "1px solid #dee2e6",
                                background: page <= 1 ? "#f8f9fa" : "#fff",
                                color: page <= 1 ? "#adb5bd" : "#495057",
                                cursor: page <= 1 ? "default" : "pointer",
                                fontSize: 13,
                            }}
                        >
                            Prev
                        </button>
                        <span style={{ fontSize: 13, color: "#6c757d" }}>
                            Page {page} of {lastPage}
                        </span>
                        <button
                            disabled={page >= lastPage}
                            onClick={() =>
                                setPage((p) => Math.min(lastPage, p + 1))
                            }
                            style={{
                                padding: "6px 14px",
                                borderRadius: 6,
                                border: "1px solid #dee2e6",
                                background:
                                    page >= lastPage ? "#f8f9fa" : "#fff",
                                color: page >= lastPage ? "#adb5bd" : "#495057",
                                cursor:
                                    page >= lastPage ? "default" : "pointer",
                                fontSize: 13,
                            }}
                        >
                            Next
                        </button>
                    </div>
                )}
            </div>

            {/* Create / Edit Modal */}
            {modal && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(0,0,0,0.45)",
                        zIndex: 9999,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: 20,
                    }}
                    onClick={closeModal}
                >
                    <div
                        style={{
                            background: "#fff",
                            borderRadius: 14,
                            maxWidth: 520,
                            width: "100%",
                            maxHeight: "90vh",
                            overflow: "auto",
                            boxShadow: "0 24px 60px rgba(0,0,0,0.2)",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                padding: "18px 22px",
                                borderBottom: "1px solid #f0f0f0",
                            }}
                        >
                            <div>
                                <div style={{ fontSize: 16, fontWeight: 600 }}>
                                    {modal === "create"
                                        ? "Add Forensic User"
                                        : "Edit Forensic User"}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: "#6c757d",
                                        marginTop: 2,
                                    }}
                                >
                                    {modal === "create"
                                        ? "Create account & issue credentials"
                                        : `Editing ${editing?.name || ""}`}
                                </div>
                            </div>
                            <button
                                onClick={closeModal}
                                style={{
                                    background: "none",
                                    border: "none",
                                    fontSize: 22,
                                    cursor: "pointer",
                                    color: "#999",
                                    padding: "0 4px",
                                }}
                            >
                                &times;
                            </button>
                        </div>
                        <div style={{ padding: "18px 22px" }}>
                            {formError && (
                                <div
                                    style={{
                                        marginBottom: 14,
                                        padding: "10px 14px",
                                        background: "rgba(229,62,62,0.1)",
                                        border: "1px solid #e53e3e",
                                        borderRadius: 8,
                                        fontSize: 12,
                                        color: "#e53e3e",
                                    }}
                                >
                                    {formError}
                                </div>
                            )}
                            <div style={{ marginBottom: 14 }}>
                                <label
                                    className="cf-label required"
                                    style={{ fontSize: 12 }}
                                >
                                    Full Name
                                </label>
                                <input
                                    className="cf-input"
                                    value={form.name}
                                    onChange={setF("name")}
                                    placeholder="e.g. Ayesha Khan"
                                    required
                                    style={{ marginTop: 6 }}
                                />
                            </div>
                            <div style={{ marginBottom: 14 }}>
                                <label
                                    className="cf-label required"
                                    style={{ fontSize: 12 }}
                                >
                                    Email (login ID)
                                </label>
                                <input
                                    className="cf-input"
                                    type="email"
                                    value={form.email}
                                    onChange={setF("email")}
                                    placeholder="forensic@nccia.gov.pk"
                                    required
                                    style={{ marginTop: 6 }}
                                />
                            </div>
                            <div style={{ marginBottom: 14 }}>
                                <label
                                    className="cf-label required"
                                    style={{ fontSize: 12 }}
                                >
                                    Role
                                </label>
                                <select
                                    className="cf-input"
                                    value={form.role}
                                    onChange={setF("role")}
                                    style={{ marginTop: 6 }}
                                >
                                    {ROLE_OPTIONS.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div style={{ marginBottom: 14 }}>
                                <label
                                    className="cf-label"
                                    style={{ fontSize: 12 }}
                                >
                                    Designation
                                </label>
                                <input
                                    className="cf-input"
                                    value={form.designation}
                                    onChange={setF("designation")}
                                    placeholder="e.g. Senior Forensic Analyst"
                                    style={{ marginTop: 6 }}
                                />
                            </div>
                            {modal === "create" ? (
                                <>
                                    <div style={{ marginBottom: 14 }}>
                                        <label
                                            className="cf-label"
                                            style={{ fontSize: 12 }}
                                        >
                                            Password{" "}
                                            {form.generate_password
                                                ? "(optional)"
                                                : "(required)"}
                                        </label>
                                        <input
                                            className="cf-input"
                                            type="password"
                                            autoComplete="new-password"
                                            minLength={15}
                                            maxLength={128}
                                            value={form.password}
                                            onChange={setF("password")}
                                            placeholder="15–128 characters, or leave empty to generate"
                                            style={{ marginTop: 6 }}
                                        />
                                        <div
                                            style={{
                                                marginTop: 10,
                                                display: "flex",
                                                alignItems: "center",
                                                gap: 8,
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                id="genpwd"
                                                checked={form.generate_password}
                                                onChange={(e) =>
                                                    setForm((prev) => ({
                                                        ...prev,
                                                        generate_password:
                                                            e.target.checked,
                                                    }))
                                                }
                                                style={{
                                                    accentColor: "#015C94",
                                                }}
                                            />
                                            <label
                                                htmlFor="genpwd"
                                                style={{
                                                    fontSize: 12.5,
                                                    color: "#2b2b2b",
                                                }}
                                            >
                                                Auto-generate a strong password
                                                (shown once)
                                            </label>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div style={{ marginBottom: 14 }}>
                                    <label
                                        className="cf-label"
                                        style={{ fontSize: 12 }}
                                    >
                                        New Password{" "}
                                        {form.password
                                            ? ""
                                            : "(leave empty to keep current)"}
                                    </label>
                                    <input
                                        className="cf-input"
                                        type="password"
                                        autoComplete="new-password"
                                        minLength={15}
                                        maxLength={128}
                                        value={form.password}
                                        onChange={setF("password")}
                                        placeholder="Leave empty to keep current password"
                                        style={{ marginTop: 6 }}
                                    />
                                </div>
                            )}
                            <div
                                style={{
                                    display: "flex",
                                    gap: 10,
                                    justifyContent: "flex-end",
                                    marginTop: 18,
                                }}
                            >
                                <button
                                    onClick={closeModal}
                                    className="btn btn-outline btn-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleSave}
                                    disabled={saving}
                                    className="btn btn-primary btn-sm"
                                >
                                    {saving
                                        ? "Saving..."
                                        : modal === "create"
                                          ? "Create User"
                                          : "Save Changes"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Credentials Modal — shown exactly once */}
            {creds && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(0,0,0,0.5)",
                        zIndex: 10000,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: 20,
                    }}
                    onClick={() => setCreds(null)}
                >
                    <div
                        style={{
                            background: "#fff",
                            borderRadius: 14,
                            maxWidth: 460,
                            width: "100%",
                            boxShadow: "0 24px 60px rgba(0,0,0,0.25)",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                padding: "20px 24px",
                                background:
                                    "linear-gradient(135deg,#0097a7,#015C94)",
                                color: "#fff",
                                borderRadius: "14px 14px 0 0",
                                display: "flex",
                                alignItems: "center",
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    width: 40,
                                    height: 40,
                                    borderRadius: "50%",
                                    background: "rgba(255,255,255,0.2)",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                }}
                            >
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#fff"
                                    strokeWidth="2"
                                >
                                    <rect
                                        x="3"
                                        y="11"
                                        width="18"
                                        height="11"
                                        rx="2"
                                    />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            </div>
                            <div>
                                <div style={{ fontSize: 16, fontWeight: 700 }}>
                                    User Credentials Issued
                                </div>
                                <div style={{ fontSize: 12, opacity: 0.9 }}>
                                    Share these credentials with the user — they
                                    will not be shown again.
                                </div>
                            </div>
                        </div>
                        <div style={{ padding: "20px 24px" }}>
                            <div
                                style={{
                                    background: "#f6f8fa",
                                    border: "1px solid #e2e8f0",
                                    borderRadius: 10,
                                    padding: "14px 16px",
                                    marginBottom: 16,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 11,
                                        textTransform: "uppercase",
                                        letterSpacing: ".05em",
                                        color: "#6c757d",
                                        marginBottom: 4,
                                    }}
                                >
                                    Login URL
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: "#015C94",
                                    }}
                                >
                                    {window.location.origin}/forensic/login
                                </div>
                                <div
                                    style={{
                                        fontSize: 11,
                                        textTransform: "uppercase",
                                        letterSpacing: ".05em",
                                        color: "#6c757d",
                                        margin: "12px 0 4px",
                                    }}
                                >
                                    Email
                                </div>
                                <div
                                    style={{
                                        fontFamily:
                                            "'JetBrains Mono', monospace",
                                        fontSize: 14,
                                        fontWeight: 600,
                                        color: "#2b2b2b",
                                    }}
                                >
                                    {creds.email}
                                </div>
                                <div
                                    style={{
                                        fontSize: 11,
                                        textTransform: "uppercase",
                                        letterSpacing: ".05em",
                                        color: "#6c757d",
                                        margin: "12px 0 4px",
                                    }}
                                >
                                    Password
                                </div>
                                <div
                                    style={{
                                        fontFamily:
                                            "'JetBrains Mono', monospace",
                                        fontSize: 14,
                                        fontWeight: 600,
                                        color: "#2b2b2b",
                                    }}
                                >
                                    {creds.password}
                                </div>
                            </div>
                            <button
                                onClick={() => setCreds(null)}
                                className="btn btn-primary"
                                style={{ width: "100%" }}
                            >
                                I&apos;ve shared the credentials
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Generate credentials confirm */}
            {genTarget && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(0,0,0,0.45)",
                        zIndex: 9999,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: 20,
                    }}
                    onClick={() => setGenTarget(null)}
                >
                    <div
                        style={{
                            background: "#fff",
                            borderRadius: 14,
                            maxWidth: 420,
                            width: "100%",
                            boxShadow: "0 24px 60px rgba(0,0,0,0.2)",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                padding: "18px 22px",
                                borderBottom: "1px solid #f0f0f0",
                            }}
                        >
                            <div style={{ fontSize: 16, fontWeight: 600 }}>
                                Generate New Credentials
                            </div>
                            <button
                                onClick={() => setGenTarget(null)}
                                style={{
                                    background: "none",
                                    border: "none",
                                    fontSize: 22,
                                    cursor: "pointer",
                                    color: "#999",
                                    padding: "0 4px",
                                }}
                            >
                                &times;
                            </button>
                        </div>
                        <div style={{ padding: "18px 22px" }}>
                            <p
                                style={{
                                    fontSize: 13,
                                    color: "#6c757d",
                                    marginBottom: 14,
                                }}
                            >
                                Generate a new random password for{" "}
                                <strong>{genTarget.name}</strong> (
                                {genTarget.email})? The current password will be
                                replaced.
                            </p>
                            <div
                                style={{
                                    display: "flex",
                                    gap: 10,
                                    justifyContent: "flex-end",
                                }}
                            >
                                <button
                                    onClick={() => setGenTarget(null)}
                                    className="btn btn-outline btn-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleGenerate}
                                    disabled={savingGen}
                                    className="btn btn-primary btn-sm"
                                >
                                    {savingGen
                                        ? "Generating..."
                                        : "Generate & Issue"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Reset Password Modal */}
            {resetTarget && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(0,0,0,0.45)",
                        zIndex: 9999,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: 20,
                    }}
                    onClick={() => {
                        setResetTarget(null);
                        setResetPwd("");
                    }}
                >
                    <div
                        style={{
                            background: "#fff",
                            borderRadius: 14,
                            maxWidth: 420,
                            width: "100%",
                            boxShadow: "0 24px 60px rgba(0,0,0,0.2)",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                padding: "18px 22px",
                                borderBottom: "1px solid #f0f0f0",
                            }}
                        >
                            <div style={{ fontSize: 16, fontWeight: 600 }}>
                                Reset Password
                            </div>
                            <button
                                onClick={() => {
                                    setResetTarget(null);
                                    setResetPwd("");
                                }}
                                style={{
                                    background: "none",
                                    border: "none",
                                    fontSize: 22,
                                    cursor: "pointer",
                                    color: "#999",
                                    padding: "0 4px",
                                }}
                            >
                                &times;
                            </button>
                        </div>
                        <div style={{ padding: "18px 22px" }}>
                            <p
                                style={{
                                    fontSize: 13,
                                    color: "#6c757d",
                                    marginBottom: 14,
                                }}
                            >
                                Set a new password for{" "}
                                <strong>{resetTarget.name}</strong> (
                                {resetTarget.email})
                            </p>
                            <label
                                className="cf-label required"
                                style={{ fontSize: 12 }}
                            >
                                New Password
                            </label>
                            <input
                                type="password"
                                autoComplete="new-password"
                                minLength={15}
                                maxLength={128}
                                className="cf-input"
                                value={resetPwd}
                                onChange={(e) => setResetPwd(e.target.value)}
                                placeholder="15–128 characters; choose an uncommon passphrase"
                                autoFocus
                                style={{ marginTop: 6 }}
                            />
                            <div
                                style={{
                                    display: "flex",
                                    gap: 10,
                                    justifyContent: "flex-end",
                                    marginTop: 18,
                                }}
                            >
                                <button
                                    onClick={() => {
                                        setResetTarget(null);
                                        setResetPwd("");
                                    }}
                                    className="btn btn-outline btn-sm"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleResetPassword}
                                    disabled={savingReset || !resetPwd}
                                    className="btn btn-primary btn-sm"
                                >
                                    {savingReset
                                        ? "Resetting..."
                                        : "Reset Password"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <ConfirmModal
                open={!!deleteTarget}
                title="Delete Forensic User"
                message={`Delete user ${deleteTarget?.name} (${deleteTarget?.email})? This will remove their forensic portal access.`}
                confirmLabel="Delete"
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
