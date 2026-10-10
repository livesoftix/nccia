"""Page rendering and OpenCV preprocessing for OCR."""

from __future__ import annotations

import math

import cv2
import pymupdf as fitz
import numpy as np

from .config import Settings


def render_page(page: fitz.Page, cfg: Settings) -> np.ndarray:
    """Render a page to an 8-bit grayscale array, capped at max_page_pixels."""
    rect = page.rect
    scale = cfg.dpi / 72.0
    pixels = rect.width * scale * rect.height * scale
    if pixels > cfg.max_page_pixels:
        scale *= math.sqrt(cfg.max_page_pixels / pixels)
    pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), colorspace=fitz.csGRAY, alpha=False)
    img = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width).copy()
    del pix
    return img


def rotate_bound(img: np.ndarray, angle: float) -> np.ndarray:
    if abs(angle) < 0.05:
        return img
    h, w = img.shape[:2]
    matrix = cv2.getRotationMatrix2D((w / 2, h / 2), angle, 1.0)
    cos, sin = abs(matrix[0, 0]), abs(matrix[0, 1])
    nw, nh = int(h * sin + w * cos), int(h * cos + w * sin)
    matrix[0, 2] += nw / 2 - w / 2
    matrix[1, 2] += nh / 2 - h / 2
    return cv2.warpAffine(img, matrix, (nw, nh), flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_CONSTANT, borderValue=255)


def estimate_skew(gray: np.ndarray, max_angle: float = 15.0) -> float:
    """Estimate small skew from the dominant text-line direction (degrees)."""
    small = cv2.resize(gray, None, fx=0.5, fy=0.5, interpolation=cv2.INTER_AREA) if gray.shape[1] > 2000 else gray
    _, bw = cv2.threshold(small, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    bw = cv2.dilate(bw, cv2.getStructuringElement(cv2.MORPH_RECT, (25, 3)))
    lines = cv2.HoughLinesP(bw, 1, np.pi / 720, threshold=150, minLineLength=bw.shape[1] // 6, maxLineGap=20)
    if lines is None:
        return 0.0
    angles = []
    for x1, y1, x2, y2 in lines[:, 0]:
        angle = math.degrees(math.atan2(y2 - y1, x2 - x1))
        if abs(angle) <= max_angle:
            angles.append(angle)
    return float(np.median(angles)) if angles else 0.0


def enhance(gray: np.ndarray) -> tuple[np.ndarray, float]:
    """Denoise, deskew and binarize. Returns (image, applied_skew_degrees)."""
    denoised = cv2.medianBlur(gray, 3)
    skew = estimate_skew(denoised)
    if abs(skew) >= 0.3:
        denoised = rotate_bound(denoised, skew)
    # Light contrast normalisation, then Otsu binarisation.
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    normalised = clahe.apply(denoised)
    _, binary = cv2.threshold(normalised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return binary, round(skew, 2)
