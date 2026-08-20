package io.fivexer.sdk;

/**
 * Base class for all Fivexer SDK errors.
 */
public class FivexerException extends RuntimeException {
    private static final long serialVersionUID = 1L;
    public FivexerException(String message) {
        super(message);
    }

    public FivexerException(String message, Throwable cause) {
        super(message, cause);
    }
}
