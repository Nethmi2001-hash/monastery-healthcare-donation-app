# Gemini AI Chatbot Integration

## Overview
The chatbot now supports **Google Gemini AI** with automatic fallback to **rule-based system**. This hybrid approach provides:
- ✅ Advanced AI responses when Gemini API is available
- ✅ Reliable fallback to rule-based responses if Gemini is unavailable
- ✅ Seamless language detection (English & Sinhala)
- ✅ Real-time system context integration

## Configuration

### Files Updated
- `chatbot_api.php` - Main chatbot endpoint
- `includes/gemini_config.php` - Gemini API configuration

### API Key Status
✅ **Gemini API Key:** `AIzaSyCNPyTzvtrD0D66HoG-pVTJHaL_syH8O84`

## How It Works

### Request Flow
```
User Query
    ↓
Language Detection (English/Sinhala)
    ↓
Try Gemini API (if enabled)
    ↓
No? Use Rule-Based System
    ↓
Return Response + Mode Info
```

### Response Format
```json
{
  "success": true,
  "response": "Answer here...",
  "language": "en",
  "mode": "gemini-ai" or "rule-based",
  "suggestions": ["Donations", "Healthcare", "Statistics", "Payment"]
}
```

## Supported Queries

### Donation Information
- "How can I donate?"
- "What are payment methods?"
- "Show donation categories"

### Healthcare Services
- "Tell me about doctors"
- "Book an appointment"
- "Medical services available"

### Statistics & Reports
- "Show statistics"
- "How many monks?"
- "System overview"

### Transparency
- "Where does money go?"
- "Show reports"
- "Accountability"

## Bilingual Support
- **English:** Automatic English responses
- **Sinhala:** Automatic Sinhala responses (Unicode: U+0D80-U+0DFF)

## API Pricing
**Google Gemini (Free Tier):**
- 60 requests per minute
- No credit card required
- Sufficient for monastery use case

**Upgrade options:**
- Gemini 1.5 Pro/Flash for higher rates
- Paid tier: ~$0.075 per 1M input tokens

## Rule-Based Fallback

The system automatically falls back to rule-based responses if:
- Gemini API is unavailable
- Network connection fails
- API rate limit exceeded
- `GEMINI_ENABLED` is set to false

### Fallback Categories
- 💰 Donations & Payment
- 🏥 Healthcare & Doctors
- 📊 Statistics & Reports
- 👥 Monks & Community
- 📂 Donation Categories
- 🔍 Transparency & Audits

## Testing

To test the chatbot:
```bash
curl -X POST http://localhost/test/chatbot_api.php \
  -H "Content-Type: application/json" \
  -d '{"message": "How can I donate?", "language": "auto"}'
```

## Configuration Options

Edit `includes/gemini_config.php`:

```php
// Enable/Disable Gemini
define('GEMINI_ENABLED', true);

// Use fallback system
define('USE_RULE_BASE_FALLBACK', true);

// Model selection
define('GEMINI_MODEL', 'gemini-pro');
```

## Security Notes

⚠️ **Important:**
- Keep API key secure
- Don't commit API key to public repositories
- Monitor API usage at Google AI Studio
- Consider moving API key to environment variables for production

## Future Enhancements

- [ ] Add conversation history tracking
- [ ] Implement user feedback system
- [ ] Add sentiment analysis
- [ ] Multi-model support (GPT, Claude, etc.)
- [ ] Advanced context awareness
- [ ] Monk & doctor profile integration
- [ ] Real-time appointment booking
- [ ] Donation analytics dashboard

## Support

For issues or questions:
1. Check `includes/gemini_config.php` configuration
2. Verify API key is valid at https://aistudio.google.com/apikey
3. Review error logs in system

---

**Last Updated:** May 8, 2026
**Status:** ✅ Active & Working
