const jwt = require('jsonwebtoken');
const config = require('../config');

const authenticate = (roles = []) => {
  const allowedRoles = Array.isArray(roles) ? roles : [roles];
  return (req, res, next) => {
    const header = req.headers.authorization || '';
    const token = header.replace('Bearer ', '');
    if (!token) {
      return res.status(401).json({ message: 'Missing token' });
    }

    try {
      const decoded = jwt.verify(token, config.jwtSecret);
      if (allowedRoles.length && !allowedRoles.includes(decoded.role)) {
        return res.status(403).json({ message: 'Forbidden' });
      }
      req.user = decoded;
      return next();
    } catch (err) {
      return res.status(401).json({ message: 'Invalid token' });
    }
  };
};

module.exports = {
  authenticate
};
