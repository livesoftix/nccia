"""NCCIA local OCR engine.

Runs entirely on the local machine: PDF text extraction (PyMuPDF), image
preprocessing (OpenCV), OCR (Tesseract, optionally PaddleOCR) and field
extraction/validation (Pydantic). It never contacts a network service and never
writes to the application database; Laravel owns authorization and persistence.
"""

__version__ = "1.0.0"
